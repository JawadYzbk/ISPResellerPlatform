<?php

namespace App\Actions;

use App\Contracts\Action;
use App\Models\InventoryItem;

final readonly class CreateInventoryItem implements Action
{
    /** @param array<string, mixed> $data */
    public function handle(array $data): InventoryItem
    {
        return InventoryItem::create([
            'sku' => strtoupper(trim((string) $data['sku'])),
            'name' => $data['name'],
            'category' => strtolower(trim((string) $data['category'])),
            'is_serialized' => (bool) $data['is_serialized'],
            'reorder_level' => $data['reorder_level'] ?? 0,
            'is_active' => true,
        ]);
    }
}
