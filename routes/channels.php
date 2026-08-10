<?php

use App\Models\Tenant;
use App\Models\User;
use Illuminate\Support\Facades\Broadcast;

Broadcast::channel('tenant.{tenantPublicId}', function (User $user, string $tenantPublicId): bool {
    $tenant = Tenant::query()->where('public_id', $tenantPublicId)->first();

    return $tenant !== null && $user->tenant_id === $tenant->id && $user->can('services.view');
});
