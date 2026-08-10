<?php

namespace App\Policies;

use App\Models\Customer;
use App\Models\User;

final class CustomerPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('customers.view');
    }

    public function view(User $user, Customer $customer): bool
    {
        return $user->tenant_id === $customer->tenant_id && $user->can('customers.view');
    }

    public function create(User $user): bool
    {
        return $user->can('customers.create');
    }

    public function update(User $user, Customer $customer): bool
    {
        return $user->tenant_id === $customer->tenant_id && $user->can('customers.update');
    }

    public function export(User $user): bool
    {
        return $user->can('customers.export');
    }

    public function anonymize(User $user, Customer $customer): bool
    {
        return $user->tenant_id === $customer->tenant_id && $user->can('customers.anonymize');
    }
}
