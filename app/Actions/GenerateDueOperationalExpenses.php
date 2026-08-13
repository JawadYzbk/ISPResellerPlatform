<?php

namespace App\Actions;

use App\Contracts\Action;
use App\Models\OperationalExpense;
use App\Models\RecurringExpenseSchedule;
use Carbon\CarbonImmutable;
use DomainException;
use Illuminate\Support\Facades\DB;

final readonly class GenerateDueOperationalExpenses implements Action
{
    public function handle(?CarbonImmutable $through = null): int
    {
        $through ??= CarbonImmutable::today();
        $generated = 0;
        $ids = RecurringExpenseSchedule::query()
            ->where('is_active', true)
            ->whereDate('next_run_on', '<=', $through)
            ->pluck('id');

        foreach ($ids as $id) {
            $generated += DB::transaction(function () use ($id, $through): int {
                $schedule = RecurringExpenseSchedule::query()->lockForUpdate()->findOrFail($id);
                $count = 0;
                $nextRunOn = CarbonImmutable::instance($schedule->next_run_on);
                while ($schedule->is_active && $nextRunOn->lte($through)) {
                    if ($schedule->ends_on !== null && $nextRunOn->gt($schedule->ends_on)) {
                        $schedule->forceFill(['is_active' => false])->save();
                        break;
                    }
                    $dueOn = $nextRunOn;
                    OperationalExpense::firstOrCreate(
                        [
                            'recurring_expense_schedule_id' => $schedule->id,
                            'recurrence_key' => $dueOn->format('Y-m-d'),
                        ],
                        [
                            'expense_category_id' => $schedule->expense_category_id,
                            'expense_vendor_id' => $schedule->expense_vendor_id,
                            'requested_by_id' => $schedule->created_by_id,
                            'status' => 'pending',
                            'payment_source' => $schedule->payment_source,
                            'amount' => $schedule->amount,
                            'currency' => $schedule->currency,
                            'description' => $schedule->description,
                            'reference' => $schedule->reference,
                            'incurred_at' => $dueOn->startOfDay(),
                        ],
                    );
                    $count++;
                    $schedule->next_run_on = match ($schedule->frequency) {
                        'weekly' => $dueOn->addWeeks($schedule->interval),
                        'monthly' => $dueOn->addMonthsNoOverflow($schedule->interval),
                        'quarterly' => $dueOn->addMonthsNoOverflow(3 * $schedule->interval),
                        'yearly' => $dueOn->addYearsNoOverflow($schedule->interval),
                        default => throw new DomainException('The recurring expense frequency is invalid.'),
                    };
                    $schedule->save();
                    $nextRunOn = CarbonImmutable::instance($schedule->next_run_on);
                }

                return $count;
            });
        }

        return $generated;
    }
}
