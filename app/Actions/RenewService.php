<?php

namespace App\Actions;

use App\Contracts\Action;
use App\Domain\Billing\AnchoredBillingCycle;
use App\Domain\Billing\BillingPeriod;
use App\Enums\ServiceStatus;
use App\Models\Plan;
use App\Models\Service;
use App\Models\ServiceEvent;
use App\Models\User;
use Carbon\CarbonImmutable;
use DomainException;
use Illuminate\Support\Facades\DB;

final readonly class RenewService implements Action
{
    public function __construct(private TransitionService $transition, private EnqueueNetworkCommand $enqueue, private QueueCustomerNotification $notify) {}

    public function handle(Service $service, ?User $actor = null, int $periods = 1): Service
    {
        if ($periods < 1 || $periods > 12) {
            throw new DomainException('Renewal periods must be between one and twelve.');
        }

        $planSyncRequired = false;
        $updated = DB::transaction(function () use ($service, $actor, $periods, &$planSyncRequired): Service {
            $locked = Service::query()->with(['plan', 'tenant'])->lockForUpdate()->findOrFail($service->id);
            if ($locked->status === ServiceStatus::Terminated) {
                throw new DomainException('Terminated services require an explicit reactivation workflow.');
            }

            $pending = $locked->metadata['pending_plan_change'] ?? null;
            if (is_array($pending) && isset($pending['plan_id'])) {
                $pendingPlan = Plan::query()->whereKey((int) $pending['plan_id'])->where('status', 'active')->first();
                if ($pendingPlan instanceof Plan && $pendingPlan->tenant_id === $locked->tenant_id) {
                    $metadata = $locked->metadata ?? [];
                    unset($metadata['pending_plan_change']);
                    $locked->forceFill(['plan_id' => $pendingPlan->id, 'metadata' => $metadata])->save();
                    $locked->setRelation('plan', $pendingPlan);
                    $planSyncRequired = $locked->status === ServiceStatus::Active;
                    $locked->loadMissing('customer');
                    ServiceEvent::create([
                        'service_id' => $locked->id,
                        'event_type' => 'plan_changed',
                        'metadata' => ['from_plan_id' => $service->plan_id, 'to_plan_id' => $pendingPlan->id, 'effective' => 'next_cycle'],
                    ]);
                }
            }

            $settings = $locked->tenant->settingsData();
            $now = CarbonImmutable::now($settings->timezone);
            $expiresAt = $locked->expires_at === null ? null : CarbonImmutable::instance($locked->expires_at)->setTimezone($settings->timezone);
            $renewedUntil = $expiresAt;
            $metadata = $locked->metadata ?? [];
            $pendingCycle = $metadata['pending_billing_cycle'] ?? null;
            $pendingAnchorDay = is_array($pendingCycle) ? (int) ($pendingCycle['anchor_day'] ?? 0) : 0;
            if ($pendingAnchorDay >= 1 && $pendingAnchorDay <= 31) {
                $base = $this->renewalBase($now, $expiresAt, (bool) ($settings->settings['grace_extends_period'] ?? false));
                $renewedUntil = (new AnchoredBillingCycle($pendingAnchorDay))->nextAnchorAfter($base);
                $locked->billing_anchor_day = $pendingAnchorDay;
                unset($metadata['pending_billing_cycle']);
                $periods--;
            }
            for ($period = 0; $period < $periods; $period++) {
                if ($locked->billing_anchor_day !== null) {
                    $base = $this->renewalBase($now, $renewedUntil, (bool) ($settings->settings['grace_extends_period'] ?? false));
                    $renewedUntil = (new AnchoredBillingCycle($locked->billing_anchor_day))->nextAnchorAfter($base);
                } else {
                    $billingPeriod = BillingPeriod::custom($renewedUntil ?? $now, max(1, (int) $locked->plan->duration_days));
                    $renewedUntil = $billingPeriod->renewFrom(
                        $now,
                        $renewedUntil,
                        (bool) ($settings->settings['grace_extends_period'] ?? false),
                    );
                }
            }
            $autoOverdue = $locked->status === ServiceStatus::Suspended && $locked->suspension_reason === 'auto_overdue';

            $locked->forceFill(['expires_at' => $renewedUntil->utc(), 'metadata' => $metadata, 'current_period_bytes' => 0, 'fup_applied_at' => null])->save();
            $updated = $autoOverdue
                ? $this->transition->handle($locked, ServiceStatus::Active, $actor, ['reason' => 'payment_renewal'])
                : $locked->refresh();

            if ($autoOverdue) {
                $this->enqueue->handle($updated, 'activate', ['reason' => 'payment_renewal']);
                $updated->loadMissing('customer');
                $this->notify->handle($updated->customer, 'service.reactivated', 'service-status:'.$updated->id.':'.$updated->desired_state_version, [
                    'customer_name' => $updated->customer->full_name,
                    'service_username' => $updated->username,
                ]);
            }

            return $updated->refresh();
        });

        if ($planSyncRequired) {
            $this->enqueue->handle($updated, 'change_plan', ['reason' => 'renewal_plan_change', 'plan_id' => $updated->plan_id]);
        }

        return $updated->refresh();
    }

    private function renewalBase(CarbonImmutable $now, ?CarbonImmutable $expiresAt, bool $graceExtendsPeriod): CarbonImmutable
    {
        return match (true) {
            $expiresAt === null => $now,
            $expiresAt->greaterThanOrEqualTo($now) => $expiresAt,
            $graceExtendsPeriod => $expiresAt,
            default => $now,
        };
    }
}
