<?php

namespace App\Jobs\Middleware;

use App\Contracts\TenantAware;
use App\Models\Tenant;
use App\Support\Tenancy;
use Closure;
use RuntimeException;

final class RestoreTenantContext
{
    public function handle(object $job, Closure $next): void
    {
        if (! $job instanceof TenantAware) {
            throw new RuntimeException('Tenant context middleware requires a TenantAware job.');
        }

        $tenant = Tenant::query()->find($job->tenantId());

        if ($tenant === null) {
            throw new RuntimeException('The tenant for this job no longer exists.');
        }

        app(Tenancy::class)->run($tenant, fn (): mixed => $next($job));
    }
}
