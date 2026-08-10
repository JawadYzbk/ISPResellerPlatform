<?php

namespace App\Actions;

use App\Contracts\Action;
use App\Enums\ServiceStatus;
use App\Models\Service;
use App\Models\Tenant;
use App\Models\UsageDaily;
use App\Support\Tenancy;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;

final readonly class EnforceServiceQuota implements Action
{
    public function __construct(private TransitionService $transition, private EnqueueNetworkCommand $enqueue) {}

    public function handle(Tenant $tenant, ?CarbonImmutable $at = null): int
    {
        return app(Tenancy::class)->run($tenant, function () use ($at): int {
            $now = $at ?? CarbonImmutable::now();
            $enforced = 0;
            Service::query()->with('plan')->where('status', ServiceStatus::Active)->chunkById(100, function ($services) use (&$enforced, $now): void {
                foreach ($services as $service) {
                    $enforced += $this->enforce($service, $now) ? 1 : 0;
                }
            });

            return $enforced;
        });
    }

    private function enforce(Service $service, CarbonImmutable $at): bool
    {
        return DB::transaction(function () use ($service, $at): bool {
            $locked = Service::query()->with('plan')->lockForUpdate()->findOrFail($service->id);
            $quota = (int) ($locked->plan->metadata['quota_bytes'] ?? 0);
            if ($quota < 1) {
                return false;
            }
            $cycleStart = $locked->expires_at === null
                ? $at->startOfMonth()
                : CarbonImmutable::instance($locked->expires_at)->subDays(max(1, (int) $locked->plan->duration_days))->startOfDay();
            $used = (int) UsageDaily::query()->where('service_id', $locked->id)->whereDate('usage_date', '>=', $cycleStart->toDateString())->whereDate('usage_date', '<=', $at->toDateString())->sum('total_octets');
            $locked->forceFill(['current_period_bytes' => $used])->save();
            if ($used < $quota || ($locked->fup_applied_at !== null && CarbonImmutable::instance($locked->fup_applied_at)->greaterThanOrEqualTo($cycleStart))) {
                return false;
            }

            $action = (string) ($locked->plan->metadata['fup_action'] ?? 'throttle');
            $metadata = ['reason' => 'quota', 'quota_bytes' => $quota, 'used_bytes' => $used, 'fup_action' => $action];
            if ($action === 'block') {
                $updated = $this->transition->handle($locked, ServiceStatus::Suspended, metadata: $metadata);
                $this->enqueue->handle($updated, 'suspend', $metadata);
            } else {
                $locked->forceFill(['fup_applied_at' => $at])->save();
                $this->enqueue->handle($locked->refresh(), 'throttle', $metadata);
            }

            return true;
        });
    }
}
