<?php

namespace App\Actions;

use App\Contracts\Action;
use App\Http\Requests\CustomerRequest;
use App\Models\Customer;

final readonly class UpdateCustomer implements Action
{
    public function handle(Customer $customer, CustomerRequest $request): Customer
    {
        $customer->update($request->validated());

        return $customer->refresh();
    }
}
