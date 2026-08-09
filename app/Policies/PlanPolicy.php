<?php

namespace App\Policies;

use App\Models\Plan;
use App\Models\User;

final class PlanPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('services.view');
    }

    public function view(User $user, Plan $plan): bool
    {
        return $user->tenant_id === $plan->tenant_id && $user->can('services.view');
    }

    public function create(User $user): bool
    {
        return $user->can('settings.manage');
    }

    public function update(User $user, Plan $plan): bool
    {
        return $user->tenant_id === $plan->tenant_id && $user->can('settings.manage');
    }
}
