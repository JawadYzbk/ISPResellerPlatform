<?php

namespace App\Actions;

use App\Contracts\Action;
use App\Models\InventoryItem;
use App\Models\StockBalance;
use App\Models\StockMovement;
use App\Models\User;
use App\Models\Warehouse;
use App\Support\StockQuantity;
use DomainException;
use Illuminate\Support\Facades\DB;

final readonly class ReceiveBulkStock implements Action
{
    public function handle(InventoryItem $item, Warehouse $warehouse, User $actor, string $quantity, ?string $note = null): StockBalance
    {
        $normalized = StockQuantity::normalize($quantity);
        if ((int) $item->tenant_id !== (int) $warehouse->tenant_id || (int) $actor->tenant_id !== (int) $item->tenant_id) {
            throw new DomainException('The item, warehouse, and actor must belong to the same tenant.');
        }
        if ($item->is_serialized) {
            throw new DomainException('Serialized inventory must be received as individual units.');
        }
        if (! $item->is_active || ! $warehouse->is_active) {
            throw new DomainException('Only active inventory items and warehouses can receive stock.');
        }

        return DB::transaction(function () use ($item, $warehouse, $actor, $normalized, $note): StockBalance {
            $balance = StockBalance::query()
                ->where('inventory_item_id', $item->id)
                ->where('warehouse_id', $warehouse->id)
                ->lockForUpdate()
                ->first();
            if ($balance === null) {
                StockBalance::create(['inventory_item_id' => $item->id, 'warehouse_id' => $warehouse->id, 'quantity' => '0.000']);
                $balance = StockBalance::query()
                    ->where('inventory_item_id', $item->id)
                    ->where('warehouse_id', $warehouse->id)
                    ->lockForUpdate()
                    ->firstOrFail();
            }

            $balance->forceFill(['quantity' => StockQuantity::add((string) $balance->quantity, $normalized)])->save();
            StockMovement::create([
                'inventory_item_id' => $item->id,
                'warehouse_id' => $warehouse->id,
                'actor_id' => $actor->id,
                'movement_type' => 'receive',
                'quantity' => $normalized,
                'note' => filled($note) ? trim($note) : null,
                'occurred_at' => now(),
            ]);

            return $balance->refresh();
        });
    }
}
