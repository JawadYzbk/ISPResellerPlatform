<?php

namespace App\Actions;

use App\Contracts\Action;
use App\Models\InventoryMovement;
use App\Models\InventoryUnit;
use App\Models\Service;
use App\Models\User;
use DomainException;
use Illuminate\Support\Facades\DB;

final readonly class AssignInventoryUnit implements Action
{
    public function handle(InventoryUnit $unit, Service $service, ?User $actor = null): InventoryUnit
    {
        return DB::transaction(function () use ($unit, $service, $actor): InventoryUnit {
            $locked = InventoryUnit::query()->lockForUpdate()->findOrFail($unit->id);
            if ($locked->status !== 'available' || $locked->service_id !== null) {
                throw new DomainException('The serialized inventory unit is already assigned.');
            }
            $locked->forceFill(['status' => 'assigned', 'service_id' => $service->id, 'assigned_at' => now()])->save();
            InventoryMovement::create(['inventory_unit_id' => $locked->id, 'from_warehouse_id' => $locked->warehouse_id, 'service_id' => $service->id, 'movement_type' => 'assign', 'actor_id' => $actor?->id]);

            return $locked->refresh();
        });
    }
}
