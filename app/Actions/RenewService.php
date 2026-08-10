<?php

namespace App\Actions;

use App\Contracts\Action;
use App\Domain\Billing\BillingPeriod;
use App\Enums\ServiceStatus;
use App\Models\Service;
use App\Models\User;
use Carbon\CarbonImmutable;
use DomainException;
use Illuminate\Support\Facades\DB;

final readonly class RenewService implements Action
{
    public function __construct(private TransitionService $transition, private EnqueueNetworkCommand $enqueue) {}

    public function handle(Service $service, ?User $actor = null): Service
    {
        return DB::transaction(function () use ($service, $actor): Service {
            $locked = Service::query()->with(['plan', 'tenant'])->lockForUpdate()->findOrFail($service->id);
            if ($locked->status === ServiceStatus::Terminated) {
                throw new DomainException('Terminated services require an explicit reactivation workflow.');
            }

            $settings = $locked->tenant->settingsData();
            $now = CarbonImmutable::now($settings->timezone);
            $expiresAt = $locked->expires_at === null ? null : CarbonImmutable::instance($locked->expires_at);
            $period = BillingPeriod::custom($expiresAt ?? $now, max(1, (int) $locked->plan->duration_days));
            $renewedUntil = $period->renewFrom(
                $now,
                $expiresAt,
                (bool) ($settings->settings['grace_extends_period'] ?? false),
            );
            $autoOverdue = $locked->status === ServiceStatus::Suspended && $locked->suspension_reason === 'auto_overdue';

            $locked->forceFill(['expires_at' => $renewedUntil->utc(), 'current_period_bytes' => 0, 'fup_applied_at' => null])->save();
            $updated = $autoOverdue
                ? $this->transition->handle($locked, ServiceStatus::Active, $actor, ['reason' => 'payment_renewal'])
                : $locked->refresh();

            if ($autoOverdue) {
                $this->enqueue->handle($updated, 'activate', ['reason' => 'payment_renewal']);
            }

            return $updated->refresh();
        });
    }
}
