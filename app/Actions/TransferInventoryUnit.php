<?php

namespace App\Actions;

use App\Contracts\Action;
use App\Models\InventoryMovement;
use App\Models\InventoryUnit;
use App\Models\User;
use App\Models\Warehouse;
use DomainException;
use Illuminate\Support\Facades\DB;

final readonly class TransferInventoryUnit implements Action
{
    public function handle(InventoryUnit $unit, Warehouse $destination, User $actor): InventoryUnit
    {
        if ((int) $unit->tenant_id !== (int) $destination->tenant_id || (int) $actor->tenant_id !== (int) $unit->tenant_id) {
            throw new DomainException('The unit, destination, and actor must belong to the same tenant.');
        }
        if (! $destination->is_active) {
            throw new DomainException('The destination warehouse is inactive.');
        }

        return DB::transaction(function () use ($unit, $destination, $actor): InventoryUnit {
            $locked = InventoryUnit::query()->lockForUpdate()->findOrFail($unit->id);
            if (! in_array($locked->status, ['available', 'returned'], true) || $locked->service_id !== null) {
                throw new DomainException('Only unassigned available or recovered units can be transferred.');
            }
            if ($locked->warehouse_id === $destination->id) {
                throw new DomainException('The unit is already in this warehouse.');
            }
            $fromWarehouse = $locked->warehouse_id;
            $wasRecovered = $locked->status === 'returned';
            $locked->forceFill(['warehouse_id' => $destination->id, 'status' => 'available'])->save();
            InventoryMovement::create([
                'inventory_unit_id' => $locked->id,
                'from_warehouse_id' => $fromWarehouse,
                'to_warehouse_id' => $destination->id,
                'movement_type' => 'transfer',
                'actor_id' => $actor->id,
                'metadata' => ['recovered' => $wasRecovered],
            ]);

            return $locked->refresh();
        });
    }
}
