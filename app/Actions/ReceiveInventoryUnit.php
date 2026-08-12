<?php

namespace App\Actions;

use App\Contracts\Action;
use App\Models\InventoryItem;
use App\Models\InventoryMovement;
use App\Models\InventoryUnit;
use App\Models\User;
use App\Models\Warehouse;
use DomainException;
use Illuminate\Support\Facades\DB;

final readonly class ReceiveInventoryUnit implements Action
{
    public function handle(InventoryItem $item, Warehouse $warehouse, User $actor, string $serialNumber): InventoryUnit
    {
        $serialNumber = trim($serialNumber);
        if ((int) $item->tenant_id !== (int) $warehouse->tenant_id || (int) $actor->tenant_id !== (int) $item->tenant_id) {
            throw new DomainException('The item, warehouse, and actor must belong to the same tenant.');
        }
        if (! $item->is_serialized) {
            throw new DomainException('Bulk items must be received with a quantity.');
        }
        if (! $item->is_active || ! $warehouse->is_active) {
            throw new DomainException('Only active items and warehouses can receive equipment.');
        }

        return DB::transaction(function () use ($item, $warehouse, $actor, $serialNumber): InventoryUnit {
            if (InventoryUnit::query()->where('serial_number', $serialNumber)->exists()) {
                throw new DomainException('That serial number is already registered in this workspace.');
            }

            $unit = InventoryUnit::create([
                'inventory_item_id' => $item->id,
                'warehouse_id' => $warehouse->id,
                'serial_number' => $serialNumber,
                'status' => 'available',
            ]);
            InventoryMovement::create([
                'inventory_unit_id' => $unit->id,
                'to_warehouse_id' => $warehouse->id,
                'movement_type' => 'receive',
                'actor_id' => $actor->id,
            ]);

            return $unit->refresh();
        });
    }
}
