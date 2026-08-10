<?php

namespace App\Actions;

use App\Contracts\Action;
use App\Models\WorkOrder;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Pagination\LengthAwarePaginator;

final readonly class ListWorkOrders implements Action
{
    /** @return LengthAwarePaginator<int, WorkOrder> */
    public function handle(?string $status, ?string $search, int $perPage = 25): LengthAwarePaginator
    {
        return WorkOrder::query()
            ->with(['customer', 'service', 'assignee'])
            ->when($status, fn (Builder $query) => $query->where('status', $status))
            ->when($search, function (Builder $query) use ($search): void {
                $term = trim($search);
                $query->where(function (Builder $query) use ($term): void {
                    $query->where('number', 'like', "%{$term}%")
                        ->orWhere('type', 'like', "%{$term}%")
                        ->orWhereHas('customer', fn (Builder $customer): Builder => $customer
                            ->where('code', 'like', "%{$term}%")
                            ->orWhere('first_name', 'like', "%{$term}%")
                            ->orWhere('last_name', 'like', "%{$term}%"));
                });
            })
            ->orderByRaw("case when status in ('assigned', 'en_route', 'in_progress') then 0 else 1 end")
            ->orderBy('scheduled_at')
            ->orderByDesc('created_at')
            ->paginate(min(max($perPage, 10), 100))
            ->withQueryString();
    }
}
