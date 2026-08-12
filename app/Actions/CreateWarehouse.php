<?php

namespace App\Actions;

use App\Contracts\Action;
use App\Models\Warehouse;

final readonly class CreateWarehouse implements Action
{
    /** @param array<string, mixed> $data */
    public function handle(array $data): Warehouse
    {
        return Warehouse::create([
            'name' => $data['name'],
            'code' => strtoupper(trim((string) $data['code'])),
            'type' => $data['type'],
            'is_active' => true,
        ]);
    }
}
