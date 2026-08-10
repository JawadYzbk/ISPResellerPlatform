<?php

namespace App\Actions;

use App\Contracts\Action;
use App\Domain\Ledger\JournalLineInput;
use App\Domain\Ledger\PostJournalEntry;
use App\Enums\NetworkState;
use App\Enums\ServiceStatus;
use App\Models\LedgerAccount;
use App\Models\Plan;
use App\Models\Service;
use App\Models\ServiceEvent;
use App\Models\User;
use Carbon\CarbonImmutable;
use DomainException;
use Illuminate\Support\Facades\DB;

final readonly class ChangeServicePlan implements Action
{
    public function __construct(private EnqueueNetworkCommand $enqueue, private PostJournalEntry $journal) {}

    public function handle(Service $service, Plan $newPlan, string $effective, ?User $actor = null): Service
    {
        if (! in_array($effective, ['immediate', 'next_cycle'], true)) {
            throw new DomainException('Plan changes must be immediate or scheduled for the next cycle.');
        }

        $updated = DB::transaction(function () use ($service, $newPlan, $effective, $actor): Service {
            $locked = Service::query()->with(['plan', 'customer', 'tenant'])->lockForUpdate()->findOrFail($service->id);
            $target = Plan::query()->whereKey($newPlan->id)->where('status', 'active')->firstOrFail();

            if ($target->tenant_id !== $locked->tenant_id || $target->id === $locked->plan_id) {
                throw new DomainException('The selected plan is not available for this service.');
            }
            if ($locked->status === ServiceStatus::Terminated) {
                throw new DomainException('Terminated services cannot change plan.');
            }

            $metadata = $locked->metadata ?? [];
            if ($effective === 'next_cycle') {
                $metadata['pending_plan_change'] = [
                    'plan_id' => $target->id,
                    'plan_public_id' => $target->public_id,
                    'requested_at' => CarbonImmutable::now()->toIso8601String(),
                ];
                $locked->forceFill(['metadata' => $metadata])->save();
                ServiceEvent::create([
                    'service_id' => $locked->id,
                    'actor_id' => $actor?->id,
                    'event_type' => 'plan_change_scheduled',
                    'metadata' => ['from_plan_id' => $locked->plan_id, 'to_plan_id' => $target->id, 'effective' => $effective],
                ]);

                return $locked->refresh();
            }

            $oldPlan = $locked->plan;
            $now = CarbonImmutable::now($locked->tenant->timezone);
            $oldPrice = $oldPlan->priceAt($now);
            $newPrice = $target->priceAt($now);
            if ($oldPrice === null || $newPrice === null) {
                throw new DomainException('Both plans need a current price before an immediate change.');
            }
            if ($oldPrice->currency !== $newPrice->currency || $oldPrice->currency !== $locked->customer->balance_currency) {
                throw new DomainException('Immediate plan changes require both prices to use the customer ledger currency.');
            }

            $periodStart = $locked->activated_at === null ? $now : CarbonImmutable::instance($locked->activated_at);
            $periodEnd = $locked->expires_at === null ? $now : CarbonImmutable::instance($locked->expires_at);
            $periodSeconds = max(1, $periodEnd->diffInSeconds($periodStart, true));
            $remainingSeconds = max(0, $now->diffInSeconds($periodEnd, false));
            $oldCredit = intdiv(($oldPrice->amount_minor * $remainingSeconds) + intdiv($periodSeconds, 2), $periodSeconds);
            $newCharge = intdiv(($newPrice->amount_minor * $remainingSeconds) + intdiv($periodSeconds, 2), $periodSeconds);

            $receivable = LedgerAccount::query()->where('code', '1100')->firstOrFail();
            $revenue = LedgerAccount::query()->where('code', '4000')->firstOrFail();
            if ($oldCredit > 0) {
                $this->journal->post(
                    'Proration credit for service plan change',
                    [
                        new JournalLineInput($revenue->id, $oldPrice->currency, debitAmount: $oldCredit, memo: $oldPlan->name),
                        new JournalLineInput($receivable->id, $oldPrice->currency, creditAmount: $oldCredit, customerId: $locked->customer_id, memo: $oldPlan->name),
                    ],
                    actor: $actor,
                    sourceType: Service::class.':proration-credit',
                    sourceId: (string) $locked->id,
                );
            }
            if ($newCharge > 0) {
                $this->journal->post(
                    'Proration charge for service plan change',
                    [
                        new JournalLineInput($receivable->id, $newPrice->currency, debitAmount: $newCharge, customerId: $locked->customer_id, memo: $target->name),
                        new JournalLineInput($revenue->id, $newPrice->currency, creditAmount: $newCharge, memo: $target->name),
                    ],
                    actor: $actor,
                    sourceType: Service::class.':proration-charge',
                    sourceId: (string) $locked->id,
                );
            }

            unset($metadata['pending_plan_change']);
            $locked->forceFill([
                'plan_id' => $target->id,
                'network_state' => NetworkState::PendingSync,
                'metadata' => $metadata,
            ])->save();
            ServiceEvent::create([
                'service_id' => $locked->id,
                'actor_id' => $actor?->id,
                'event_type' => 'plan_changed',
                'metadata' => [
                    'from_plan_id' => $oldPlan->id,
                    'to_plan_id' => $target->id,
                    'effective' => $effective,
                    'remaining_seconds' => $remainingSeconds,
                    'old_credit_amount' => $oldCredit,
                    'new_charge_amount' => $newCharge,
                    'currency' => $newPrice->currency,
                ],
            ]);

            return $locked->refresh();
        });

        if ($effective === 'immediate' && $updated->status !== ServiceStatus::Pending) {
            $this->enqueue->handle($updated, 'change_plan', ['reason' => 'operator_plan_change', 'plan_id' => $updated->plan_id]);
        }

        return $updated->refresh();
    }
}
