<?php

namespace App\Actions;

use App\Contracts\Action;
use App\Models\Supplier;
use App\Models\SupplierBill;

final readonly class CreateSupplierBill implements Action
{
    /** @param array<string, mixed> $data */
    public function handle(Supplier $supplier, array $data): SupplierBill
    {
        return $supplier->bills()->create([
            'reference' => trim((string) $data['reference']),
            'period_start' => $data['period_start'],
            'period_end' => $data['period_end'],
            'amount' => $data['amount'],
            'currency' => strtoupper((string) $data['currency']),
            'status' => 'open',
            'notes' => filled($data['notes'] ?? null) ? trim((string) $data['notes']) : null,
        ]);
    }
}
