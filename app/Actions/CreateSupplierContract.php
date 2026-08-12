<?php

namespace App\Actions;

use App\Contracts\Action;
use App\Models\Supplier;
use App\Models\SupplierContract;

final readonly class CreateSupplierContract implements Action
{
    /** @param array<string, mixed> $data */
    public function handle(Supplier $supplier, array $data): SupplierContract
    {
        return $supplier->contracts()->create([
            'service_type' => trim((string) $data['service_type']),
            'terms' => $data['terms'] ?? null,
            'wholesale_currency' => strtoupper((string) $data['wholesale_currency']),
            'effective_from' => $data['effective_from'],
            'effective_to' => $data['effective_to'] ?? null,
            'status' => $data['status'] ?? 'active',
        ]);
    }
}
