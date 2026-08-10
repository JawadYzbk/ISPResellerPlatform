<?php

namespace App\Actions;

use App\Contracts\Action;
use App\Models\InventoryMovement;
use App\Models\InventoryUnit;
use App\Models\Service;
use App\Models\User;
use DomainException;
use Illuminate\Support\Facades\DB;

final readonly class ReturnServiceInventory implements Action
{
    public function handle(Service $service, ?User $actor = null): int
    {
        if ($actor !== null && (int) $actor->tenant_id !== (int) $service->tenant_id) {
            throw new DomainException('The service and inventory return actor must belong to the same tenant.');
        }

        return DB::transaction(function () use ($service, $actor): int {
            $units = InventoryUnit::query()->where('service_id', $service->id)->lockForUpdate()->get();
            foreach ($units as $unit) {
                $unit->forceFill(['status' => 'returned', 'service_id' => null, 'returned_at' => now()])->save();
                InventoryMovement::create([
                    'inventory_unit_id' => $unit->id,
                    'from_warehouse_id' => null,
                    'to_warehouse_id' => $unit->warehouse_id,
                    'service_id' => $service->id,
                    'movement_type' => 'return',
                    'actor_id' => $actor?->id,
                    'metadata' => ['reason' => 'service_terminated'],
                ]);
            }

            return $units->count();
        });
    }
}
