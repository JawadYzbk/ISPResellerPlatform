<?php

namespace App\Actions;

use App\Models\Customer;

final readonly class GetCustomerDetails
{
    public function handle(Customer $customer): Customer
    {
        return $customer->load(['zone', 'services.plan', 'services.events' => fn ($query) => $query->latest()->limit(10)]);
    }
}
