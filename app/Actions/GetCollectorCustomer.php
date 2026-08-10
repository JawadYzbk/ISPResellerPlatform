<?php

namespace App\Actions;

use App\Contracts\Action;
use App\Models\Customer;
use App\Models\Payment;
use Carbon\CarbonImmutable;

final readonly class GetCollectorCustomer implements Action
{
    /** @return array<string, mixed> */
    public function handle(Customer $customer): array
    {
        $customer->load(['zone', 'services.plan']);
        $lastPayment = $customer->payments()->latest('received_at')->first();
        $now = CarbonImmutable::now();

        return [
            'id' => $customer->public_id,
            'code' => $customer->code,
            'name' => $customer->full_name,
            'phone' => $customer->phone,
            'email' => $customer->email,
            'address' => $customer->address,
            'latitude' => $customer->latitude,
            'longitude' => $customer->longitude,
            'directions_url' => $customer->latitude === null || $customer->longitude === null ? null : 'https://www.google.com/maps/dir/?api=1&destination='.rawurlencode($customer->latitude.','.$customer->longitude),
            'zone' => $customer->zone === null ? null : ['code' => $customer->zone->code, 'name' => $customer->zone->name],
            'balance' => ['amount' => $customer->balance_amount, 'currency' => $customer->balance_currency],
            'services' => $customer->services->map(fn ($service): array => [
                'id' => $service->public_id,
                'username' => $service->username,
                'status' => $service->status->value,
                'network_state' => $service->network_state->value,
                'expires_at' => $service->expires_at?->toIso8601String(),
                'days_remaining' => $service->expires_at === null ? null : (int) $now->diffInDays($service->expires_at, false),
                'plan' => $service->plan === null ? null : ['id' => $service->plan->public_id, 'name' => $service->plan->name, 'download_kbps' => $service->plan->download_kbps, 'upload_kbps' => $service->plan->upload_kbps],
            ])->values(),
            'last_payment' => $lastPayment instanceof Payment ? [
                'id' => $lastPayment->public_id,
                'receipt_number' => $lastPayment->number,
                'amount' => $lastPayment->amount,
                'currency' => $lastPayment->currency,
                'received_at' => $lastPayment->received_at?->toIso8601String(),
            ] : null,
        ];
    }
}
