<?php

namespace App\Actions;

use App\Contracts\Action;
use App\Models\Customer;
use Illuminate\Foundation\Http\FormRequest;

final readonly class UpdateCustomer implements Action
{
    public function handle(Customer $customer, FormRequest $request): Customer
    {
        $customer->update($request->validated());

        return $customer->refresh();
    }
}
