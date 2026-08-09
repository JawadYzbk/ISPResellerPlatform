<?php

namespace App\Actions;

use App\Contracts\Action;
use App\Models\Customer;

final readonly class GetCustomerDetails implements Action
{
    public function handle(Customer $customer): Customer
    {
        return $customer->load(['zone', 'services.plan', 'services.events' => fn ($query) => $query->latest()->limit(10)]);
    }
}
