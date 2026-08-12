<?php

namespace App\Actions;

use App\Contracts\Action;
use App\Models\Supplier;

final readonly class UpdateSupplier implements Action
{
    /** @param array<string, mixed> $data */
    public function handle(Supplier $supplier, array $data): Supplier
    {
        $supplier->update([
            'name' => trim((string) $data['name']),
            'code' => strtoupper(trim((string) $data['code'])),
            'contact_email' => filled($data['contact_email'] ?? null) ? trim((string) $data['contact_email']) : null,
            'is_active' => (bool) $data['is_active'],
        ]);

        return $supplier->refresh();
    }
}
