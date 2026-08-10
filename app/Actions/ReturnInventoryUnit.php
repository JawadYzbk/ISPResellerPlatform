<?php

namespace App\Actions;

use App\Contracts\Action;
use App\Models\InventoryMovement;
use App\Models\InventoryUnit;
use App\Models\Service;
use App\Models\User;
use DomainException;
use Illuminate\Support\Facades\DB;

final readonly class ReturnInventoryUnit implements Action
{
    public function handle(InventoryUnit $unit, Service $service, ?User $actor = null): InventoryUnit
    {
        return DB::transaction(function () use ($unit, $service, $actor): InventoryUnit {
            $locked = InventoryUnit::query()->lockForUpdate()->findOrFail($unit->id);
            if ($locked->tenant_id !== $service->tenant_id || ($actor !== null && $actor->tenant_id !== $locked->tenant_id)) {
                throw new DomainException('Inventory units, services, and actors must belong to the same tenant.');
            }
            if ($locked->service_id !== $service->id || $locked->status !== 'assigned') {
                throw new DomainException('The inventory unit is not assigned to this service.');
            }

            $locked->forceFill(['status' => 'returned', 'service_id' => null, 'returned_at' => now()])->save();
            InventoryMovement::create([
                'inventory_unit_id' => $locked->id,
                'from_warehouse_id' => null,
                'to_warehouse_id' => $locked->warehouse_id,
                'service_id' => $service->id,
                'movement_type' => 'return',
                'actor_id' => $actor?->id,
                'metadata' => ['reason' => 'operator_return'],
            ]);

            return $locked->refresh();
        });
    }
}
