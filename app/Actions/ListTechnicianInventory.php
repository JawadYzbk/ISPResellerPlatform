<?php

namespace App\Actions;

use App\Contracts\Action;
use App\Models\InventoryUnit;
use App\Models\User;
use App\Models\Warehouse;
use App\Support\StockQuantity;

final readonly class ListTechnicianInventory implements Action
{
    /** @return list<array<string, mixed>> */
    public function handle(User $technician): array
    {
        return Warehouse::query()
            ->where('assigned_user_id', $technician->id)
            ->where('is_active', true)
            ->with(['units.item', 'stockBalances.item'])
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
                'bulk' => $warehouse->stockBalances
                    ->filter(fn ($balance): bool => $balance->item->is_active && ! $balance->item->is_serialized && StockQuantity::greaterThan((string) $balance->quantity, '0.000'))
                    ->map(fn ($balance): array => [
                        'sku' => $balance->item->sku,
                        'name' => $balance->item->name,
                        'quantity' => (string) $balance->quantity,
                    ])->values()->all(),
            ])->values()->all();
    }
}
