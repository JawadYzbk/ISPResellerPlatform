<?php

namespace App\Actions;

use App\Contracts\Action;
use App\Enums\CustomerStatus;
use App\Http\Requests\CustomerRequest;
use App\Models\Customer;
use App\Models\Tenant;
use App\Support\CustomerCodeGenerator;

final readonly class CreateCustomer implements Action
{
    public function __construct(private CustomerCodeGenerator $codeGenerator) {}

    public function handle(CustomerRequest $request, Tenant $tenant): Customer
    {
        return Customer::create([
            ...$request->validated(),
            'code' => $this->codeGenerator->next(),
            'status' => CustomerStatus::Active,
            'balance_currency' => $tenant->collection_currency,
        ]);
    }
}
