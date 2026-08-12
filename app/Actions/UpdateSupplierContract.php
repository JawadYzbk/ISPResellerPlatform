<?php

namespace App\Actions;

use App\Contracts\Action;
use App\Models\SupplierContract;

final readonly class UpdateSupplierContract implements Action
{
    /** @param array<string, mixed> $data */
    public function handle(SupplierContract $contract, array $data): SupplierContract
    {
        $contract->update([
            'service_type' => trim((string) $data['service_type']),
            'wholesale_currency' => strtoupper((string) $data['wholesale_currency']),
            'effective_from' => $data['effective_from'],
            'effective_to' => $data['effective_to'] ?? null,
            'status' => $data['status'],
        ]);

        return $contract->refresh();
    }
}
