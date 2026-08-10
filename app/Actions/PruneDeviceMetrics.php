<?php

namespace App\Actions;

use App\Contracts\Action;
use App\Models\DeviceMetric;
use App\Models\Tenant;
use App\Support\Tenancy;
use Carbon\CarbonImmutable;

final readonly class PruneDeviceMetrics implements Action
{
    public function handle(Tenant $tenant, int $retentionDays = 90, ?CarbonImmutable $at = null): int
    {
        return app(Tenancy::class)->run($tenant, function () use ($retentionDays, $at): int {
            $cutoff = ($at ?? CarbonImmutable::now())->subDays(max(1, $retentionDays));

            return DeviceMetric::query()->where('observed_at', '<', $cutoff)->delete();
        });
    }
}
