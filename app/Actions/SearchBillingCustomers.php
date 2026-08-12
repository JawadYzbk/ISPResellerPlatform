<?php

namespace App\Actions;

use App\Contracts\Action;
use App\Models\Customer;

final readonly class SearchBillingCustomers implements Action
{
    /**
     * @return list<array{id: string, code: string, name: string, phone: string|null, status: string, balance_amount: int, balance_currency: string}>
     */
    public function handle(?string $search, int $limit = 25): array
    {
        return Customer::query()
            ->search($search)
            ->orderBy('first_name')
            ->orderBy('last_name')
            ->limit(min(max($limit, 10), 50))
            ->get(['public_id', 'code', 'first_name', 'last_name', 'phone', 'status', 'balance_amount', 'balance_currency'])
            ->map(fn (Customer $customer): array => $this->option($customer))
            ->values()
            ->all();
    }

    /**
     * @return array{id: string, code: string, name: string, phone: string|null, status: string, balance_amount: int, balance_currency: string}
     */
    public function option(Customer $customer): array
    {
        return [
            'id' => $customer->public_id,
            'code' => $customer->code,
            'name' => $customer->full_name,
            'phone' => $customer->phone,
            'status' => $customer->status->value,
            'balance_amount' => $customer->balance_amount,
            'balance_currency' => $customer->balance_currency,
        ];
    }
}
