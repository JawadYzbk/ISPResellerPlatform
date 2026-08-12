<?php

namespace App\Actions;

use App\Contracts\Action;
use App\Models\InventoryItem;
use DomainException;
use Illuminate\Support\Facades\DB;

final readonly class UpdateInventoryItem implements Action
{
    /** @param array{sku: string, name: string, category: string, is_serialized: bool, reorder_level: int|string, is_active: bool} $data */
    public function handle(InventoryItem $item, array $data): InventoryItem
    {
        return DB::transaction(function () use ($item, $data): InventoryItem {
            $locked = InventoryItem::query()->lockForUpdate()->findOrFail($item->id);
            if ($locked->is_serialized !== (bool) $data['is_serialized'] && $locked->units()->exists()) {
                throw new DomainException('Inventory type cannot change after serialized units have been received.');
            }

            $locked->forceFill([
                'sku' => strtoupper(trim($data['sku'])),
                'name' => trim($data['name']),
                'category' => strtolower(trim($data['category'])),
                'is_serialized' => (bool) $data['is_serialized'],
                'reorder_level' => (int) $data['reorder_level'],
                'is_active' => (bool) $data['is_active'],
            ])->save();

            return $locked->refresh();
        });
    }
}
