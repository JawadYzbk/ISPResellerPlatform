<?php

namespace App\Actions;

use App\Contracts\Action;
use App\Enums\CashShiftStatus;
use App\Models\CashShift;
use App\Models\User;
use DomainException;
use Illuminate\Support\Facades\DB;

final readonly class CloseCashShift implements Action
{
    /** @param array<string, int> $declaredTotals */
    public function handle(CashShift $shift, array $declaredTotals, ?string $varianceNote = null, ?User $actor = null): CashShift
    {
        return DB::transaction(function () use ($shift, $declaredTotals, $varianceNote): CashShift {
            $locked = CashShift::query()->lockForUpdate()->findOrFail($shift->id);
            if ($locked->status !== CashShiftStatus::Open) {
                throw new DomainException('The cash shift is already closed.');
            }
            $systemTotals = array_map('intval', $locked->opening_float ?? []);
            foreach ($locked->payments()->where('status', 'posted')->selectRaw('currency, sum(amount) as total')->groupBy('currency')->pluck('total', 'currency') as $currency => $total) {
                $systemTotals[$currency] = ($systemTotals[$currency] ?? 0) + (int) $total;
            }
            $currencies = array_unique([...array_keys($systemTotals), ...array_keys($declaredTotals)]);
            $systemTotals = array_reduce($currencies, function (array $totals, string $currency) use ($systemTotals): array {
                $totals[$currency] = $systemTotals[$currency] ?? 0;

                return $totals;
            }, []);
            $declaredTotals = array_reduce($currencies, function (array $totals, string $currency) use ($declaredTotals): array {
                $totals[$currency] = (int) ($declaredTotals[$currency] ?? 0);

                return $totals;
            }, []);
            ksort($systemTotals);
            ksort($declaredTotals);
            $variance = $systemTotals !== $declaredTotals;
            if ($variance && blank($varianceNote)) {
                throw new DomainException('A variance note is required when declared cash does not match the system total.');
            }
            $locked->forceFill(['status' => CashShiftStatus::Closed, 'closed_at' => now(), 'system_totals' => $systemTotals, 'declared_totals' => $declaredTotals, 'variance' => $variance, 'variance_note' => $varianceNote])->save();

            return $locked->refresh();
        });
    }
}
