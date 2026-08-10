<?php

namespace App\Support\Api;

use App\Models\Customer;
use App\Models\Service;

final readonly class CustomerApiResource
{
    public function __construct(private ServiceApiResource $services) {}

    /** @return array<string, mixed> */
    public function make(Customer $customer): array
    {
        $customer->loadMissing(['zone', 'services.plan', 'services.router', 'services.events']);

        return [
            'id' => $customer->public_id,
            'code' => $customer->code,
            'first_name' => $customer->first_name,
            'last_name' => $customer->last_name,
            'phone' => $customer->phone,
            'email' => $customer->email,
            'address' => $customer->address,
            'latitude' => $customer->latitude,
            'longitude' => $customer->longitude,
            'status' => $customer->status->value,
            'balance_amount' => $customer->balance_amount,
            'balance_currency' => $customer->balance_currency,
            'zone' => $customer->zone === null ? null : [
                'code' => $customer->zone->code,
                'name' => $customer->zone->name,
            ],
            'services' => $customer->services->map(fn (Service $service): array => $this->services->make($service))->values()->all(),
        ];
    }
}
