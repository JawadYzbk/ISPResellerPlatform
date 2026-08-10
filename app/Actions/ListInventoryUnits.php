<?php

namespace App\Actions;

use App\Contracts\Action;
use App\Models\InventoryUnit;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Pagination\LengthAwarePaginator;

final readonly class ListInventoryUnits implements Action
{
    /** @return LengthAwarePaginator<int, InventoryUnit> */
    public function handle(?string $status, ?string $search, int $perPage = 25): LengthAwarePaginator
    {
        return InventoryUnit::query()
            ->with(['item', 'warehouse', 'service.customer'])
            ->when($status, fn (Builder $query) => $query->where('status', $status))
            ->when($search, function (Builder $query) use ($search): void {
                $term = trim($search);
                $query->where(function (Builder $query) use ($term): void {
                    $query->where('serial_number', 'like', "%{$term}%")
                        ->orWhereHas('item', fn (Builder $item): Builder => $item
                            ->where('sku', 'like', "%{$term}%")
                            ->orWhere('name', 'like', "%{$term}%"))
                        ->orWhereHas('service', fn (Builder $service): Builder => $service->where('username', 'like', "%{$term}%"));
                });
            })
            ->orderBy('status')
            ->orderBy('serial_number')
            ->paginate(min(max($perPage, 10), 100))
            ->withQueryString();
    }
}
