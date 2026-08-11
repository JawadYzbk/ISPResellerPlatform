<?php

namespace App\Actions;

use App\Contracts\Action;
use App\Models\CurrentSession;
use App\Models\Tenant;
use App\Support\Tenancy;
use Carbon\CarbonImmutable;

final readonly class CloseSessionsForNas implements Action
{
    public function handle(Tenant $tenant, string $nasname, ?CarbonImmutable $at = null, string $cause = 'NAS-Reboot'): int
    {
        $closedAt = $at ?? CarbonImmutable::now();

        return app(Tenancy::class)->run($tenant, function () use ($nasname, $closedAt, $cause): int {
            return CurrentSession::query()
                ->where('nasname', $nasname)
                ->whereNull('stopped_at')
                ->update([
                    'stopped_at' => $closedAt,
                    'terminate_cause' => $cause,
                    'updated_at' => now(),
                ]);
        });
    }
}
