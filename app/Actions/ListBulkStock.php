<?php

namespace App\Actions;

use App\Contracts\Action;
use App\Models\StockBalance;
use App\Models\Warehouse;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;

final readonly class ListBulkStock implements Action
{
    /** @return Collection<int, StockBalance> */
    public function handle(?Warehouse $warehouse = null): Collection
    {
        return StockBalance::query()
            ->with(['item', 'warehouse'])
            ->where('quantity', '>', 0)
            ->whereHas('item', fn (Builder $query): Builder => $query->where('is_serialized', false)->where('is_active', true))
            ->when($warehouse, fn (Builder $query): Builder => $query->where('warehouse_id', $warehouse->id))
            ->orderBy('warehouse_id')
            ->orderBy('inventory_item_id')
            ->get();
    }
}
