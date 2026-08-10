<?php

namespace App\Actions;

use App\Contracts\Action;
use App\Models\InventoryMovement;
use App\Models\StockMovement;
use Illuminate\Database\Eloquent\Builder;

final readonly class ListInventoryMovements implements Action
{
    /** @return list<array<string, mixed>> */
    public function handle(?string $movementType = null, int $limit = 100): array
    {
        $serialized = InventoryMovement::query()
            ->with(['unit.item', 'fromWarehouse', 'toWarehouse', 'service', 'actor'])
            ->when($movementType, fn (Builder $query): Builder => $query->where('movement_type', $movementType))
            ->latest()
            ->limit($limit)
            ->get()
            ->map(fn (InventoryMovement $movement): array => [
                'id' => 'serialized-'.$movement->id,
                'movement_type' => $movement->movement_type,
                'kind' => 'serialized',
                'occurred_at' => $movement->created_at?->toIso8601String(),
                'item' => $movement->unit?->item === null ? null : ['sku' => $movement->unit->item->sku, 'name' => $movement->unit->item->name],
                'serial_number' => $movement->unit?->serial_number,
                'from_warehouse' => $movement->fromWarehouse?->code,
                'to_warehouse' => $movement->toWarehouse?->code,
                'quantity' => 1,
                'reference' => $movement->service?->username,
                'actor' => $movement->actor?->name,
                'note' => is_array($movement->metadata) ? ($movement->metadata['reason'] ?? null) : null,
            ]);

        $bulk = StockMovement::query()
            ->with(['item', 'warehouse', 'workOrder', 'actor'])
            ->when($movementType, fn (Builder $query): Builder => $query->where('movement_type', $movementType))
            ->latest('occurred_at')
            ->limit($limit)
            ->get()
            ->map(fn (StockMovement $movement): array => [
                'id' => 'bulk-'.$movement->id,
                'movement_type' => $movement->movement_type,
                'kind' => 'bulk',
                'occurred_at' => $movement->occurred_at?->toIso8601String(),
                'item' => $movement->item === null ? null : ['sku' => $movement->item->sku, 'name' => $movement->item->name],
                'serial_number' => null,
                'from_warehouse' => null,
                'to_warehouse' => $movement->warehouse?->code,
                'quantity' => (string) $movement->quantity,
                'reference' => $movement->workOrder?->public_id,
                'actor' => $movement->actor?->name,
                'note' => $movement->note,
            ]);

        return $serialized
            ->concat($bulk)
            ->sortByDesc('occurred_at')
            ->take($limit)
            ->values()
            ->all();
    }
}
