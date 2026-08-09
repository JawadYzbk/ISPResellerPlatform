<?php

namespace App\Models\Concerns;

use App\Models\Tenant;
use App\Support\TenantScope;
use App\Support\Tenancy;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use LogicException;

trait BelongsToTenant
{
    protected static function bootBelongsToTenant(): void
    {
        static::addGlobalScope(new TenantScope());

        static::creating(function ($model): void {
            if ($model->getAttribute('tenant_id') === null) {
                $tenantId = app(Tenancy::class)->id();

                if ($tenantId === null) {
                    throw new LogicException('Tenant-owned records must be created inside a tenant context.');
                }

                $model->setAttribute('tenant_id', $tenantId);
            }
        });
    }

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }
}
