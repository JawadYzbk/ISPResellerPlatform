<?php

use App\Models\User;
use Illuminate\Support\Facades\Broadcast;

Broadcast::channel('tenant.{tenantId}', function (User $user, int $tenantId): bool {
    return $user->tenant_id === $tenantId && $user->can('services.view');
});
