<?php

namespace App\Actions;

use App\Contracts\Action;
use App\Models\Supplier;

final readonly class CreateSupplier implements Action
{
    /** @param array<string, mixed> $data */
    public function handle(array $data): Supplier
    {
        return Supplier::create([
            'name' => trim((string) $data['name']),
            'code' => strtoupper(trim((string) $data['code'])),
            'contact_email' => filled($data['contact_email'] ?? null) ? trim((string) $data['contact_email']) : null,
            'is_active' => true,
        ]);
    }
}
