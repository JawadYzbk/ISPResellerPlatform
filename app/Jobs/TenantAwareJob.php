<?php

namespace App\Jobs;

use App\Contracts\TenantAware;
use App\Jobs\Middleware\RestoreTenantContext;
use App\Support\Tenancy;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

abstract class TenantAwareJob implements ShouldQueue, TenantAware
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tenantId;

    public function __construct(?int $tenantId = null)
    {
        $this->tenantId = $tenantId ?? app(Tenancy::class)->requireId();
    }

    public function tenantId(): int
    {
        return $this->tenantId;
    }

    /** @return list<object> */
    public function middleware(): array
    {
        return [new RestoreTenantContext];
    }
}
