<?php

namespace App\Actions;

use App\Contracts\Action;
use App\Models\CurrentSession;
use App\Models\Tenant;
use App\Support\Tenancy;
use Carbon\CarbonImmutable;

final readonly class MarkStaleSessions implements Action
{
    public function handle(Tenant $tenant, int $interimIntervalSeconds = 300, ?CarbonImmutable $at = null): int
    {
        return app(Tenancy::class)->run($tenant, function () use ($interimIntervalSeconds, $at): int {
            $cutoff = ($at ?? CarbonImmutable::now())->subSeconds(max(1, $interimIntervalSeconds) * 2);

            return CurrentSession::query()
                ->whereNull('stopped_at')
                ->where('last_seen_at', '<', $cutoff)
                ->update(['stopped_at' => $at ?? now(), 'terminate_cause' => 'Stale', 'updated_at' => now()]);
        });
    }
}
