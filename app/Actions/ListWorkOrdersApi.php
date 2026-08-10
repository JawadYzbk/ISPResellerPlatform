<?php

namespace App\Actions;

use App\Contracts\Action;
use App\Models\WorkOrder;
use App\Support\Api\WorkOrderApiResource;
use Illuminate\Contracts\Pagination\CursorPaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Spatie\QueryBuilder\AllowedFilter;
use Spatie\QueryBuilder\AllowedSort;
use Spatie\QueryBuilder\QueryBuilder;

final readonly class ListWorkOrdersApi implements Action
{
    public function __construct(private WorkOrderApiResource $resource) {}

    /** @return CursorPaginator<int, array<string, mixed>> */
    public function handle(Request $request, int $perPage = 20): CursorPaginator
    {
        return QueryBuilder::for(WorkOrder::query())
            ->allowedFilters([
                AllowedFilter::exact('status'),
                AllowedFilter::exact('type'),
                AllowedFilter::callback('search', function (Builder $query, mixed $value): void {
                    $term = trim((string) $value);
                    if ($term !== '') {
                        $query->where(function (Builder $order) use ($term): void {
                            $order->where('number', 'like', "%{$term}%")
                                ->orWhere('type', 'like', "%{$term}%")
                                ->orWhereHas('customer', fn (Builder $customer): Builder => $customer
                                    ->where('code', 'like', "%{$term}%")
                                    ->orWhere('first_name', 'like', "%{$term}%")
                                    ->orWhere('last_name', 'like', "%{$term}%"));
                        });
                    }
                }),
            ])
            ->allowedSorts([AllowedSort::field('created_at'), AllowedSort::field('scheduled_at'), AllowedSort::field('status'), AllowedSort::field('type')])
            ->defaultSort('scheduled_at')
            ->with(['customer', 'service', 'assignee'])
            ->cursorPaginate(min(max($perPage, 10), 100))
            ->withQueryString()
            ->through(fn (WorkOrder $order): array => $this->resource->make($order, false));
    }
}
