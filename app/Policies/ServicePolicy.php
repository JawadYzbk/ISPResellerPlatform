<?php

namespace App\Policies;

use App\Models\Service;
use App\Models\User;

final class ServicePolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('services.view');
    }

    public function view(User $user, Service $service): bool
    {
        return $user->tenant_id === $service->tenant_id && $user->can('services.view');
    }

    public function create(User $user): bool
    {
        return $user->can('services.create');
    }

    public function activate(User $user, Service $service): bool
    {
        return $user->tenant_id === $service->tenant_id && $user->can('services.activate');
    }

    public function suspend(User $user, Service $service): bool
    {
        return $user->tenant_id === $service->tenant_id && $user->can('services.suspend');
    }

    public function terminate(User $user, Service $service): bool
    {
        return $user->tenant_id === $service->tenant_id && $user->can('services.terminate');
    }

    public function disconnect(User $user, Service $service): bool
    {
        return $user->tenant_id === $service->tenant_id && $user->can('network.disconnect');
    }
}
