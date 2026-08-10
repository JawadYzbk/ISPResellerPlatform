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
    public function __construct(private EnqueueNetworkCommand $enqueue, private PostJournalEntry $journal, private PreviewServicePlanChange $preview) {}

    public function handle(Service $service, Plan $newPlan, string $effective, ?User $actor = null): Service
    {
        if (! in_array($effective, ['immediate', 'next_cycle'], true)) {
            throw new DomainException('Plan changes must be immediate or scheduled for the next cycle.');
        }

        $updated = DB::transaction(function () use ($service, $newPlan, $effective, $actor): Service {
            $locked = Service::query()->with(['plan', 'customer', 'tenant'])->lockForUpdate()->findOrFail($service->id);
            $target = Plan::query()->whereKey($newPlan->id)->where('status', 'active')->firstOrFail();

            $preview = $this->preview->handle($locked, $target, $effective);

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
            $oldCredit = (int) $preview['old_credit_amount'];
            $newCharge = (int) $preview['new_charge_amount'];
            $currency = (string) $preview['currency'];

            $receivable = LedgerAccount::query()->where('code', '1100')->firstOrFail();
            $revenue = LedgerAccount::query()->where('code', '4000')->firstOrFail();
            if ($oldCredit > 0) {
                $this->journal->post(
                    'Proration credit for service plan change',
                    [
                        new JournalLineInput($revenue->id, $currency, debitAmount: $oldCredit, memo: $oldPlan->name),
                        new JournalLineInput($receivable->id, $currency, creditAmount: $oldCredit, customerId: $locked->customer_id, memo: $oldPlan->name),
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
                        new JournalLineInput($receivable->id, $currency, debitAmount: $newCharge, customerId: $locked->customer_id, memo: $target->name),
                        new JournalLineInput($revenue->id, $currency, creditAmount: $newCharge, memo: $target->name),
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
                    'remaining_seconds' => $preview['remaining_seconds'],
                    'old_credit_amount' => $oldCredit,
                    'new_charge_amount' => $newCharge,
                    'currency' => $currency,
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
