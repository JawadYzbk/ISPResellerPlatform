<?php

namespace App\Actions;

use App\Contracts\Action;
use App\Models\InventoryUnit;
use App\Models\User;
use App\Models\Warehouse;

final readonly class ListTechnicianInventory implements Action
{
    /** @return list<array<string, mixed>> */
    public function handle(User $technician): array
    {
        return Warehouse::query()
            ->where('assigned_user_id', $technician->id)
            ->where('is_active', true)
            ->with('units.item')
            ->orderBy('code')
            ->get()
            ->map(fn (Warehouse $warehouse): array => [
                'code' => $warehouse->code,
                'name' => $warehouse->name,
                'units' => $warehouse->units->map(fn (InventoryUnit $unit): array => [
                    'sku' => $unit->item->sku,
                    'name' => $unit->item->name,
                    'serial_number' => $unit->serial_number,
                    'status' => $unit->status,
                ])->values()->all(),
            ])->values()->all();
    }
}
