<?php

namespace App\Support;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Scope;

final class TenantScope implements Scope
{
    public function apply(Builder $builder, Model $model): void
    {
        $tenantId = app(Tenancy::class)->id();

        $builder->where(
            $model->qualifyColumn('tenant_id'),
            $tenantId ?? -1,
        );
    }
}
