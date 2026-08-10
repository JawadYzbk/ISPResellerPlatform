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
    public function __construct(private CustomerCodeGenerator $codeGenerator, private QueueCustomerNotification $notify) {}

    public function handle(CustomerRequest $request, Tenant $tenant): Customer
    {
        $customer = Customer::create([
            ...$request->validated(),
            'code' => $this->codeGenerator->next(),
            'status' => CustomerStatus::Active,
            'balance_currency' => $tenant->collection_currency,
        ]);

        $this->notify->handle($customer, 'customer.welcome', 'customer-welcome:'.$customer->id, [
            'customer_name' => $customer->full_name,
            'customer_code' => $customer->code,
        ]);

        return $customer;
    }
}
