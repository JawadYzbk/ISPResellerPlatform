<?php

namespace App\Actions;

use App\Contracts\Action;
use App\Models\Zone;
use App\Support\Api\ZoneApiResource;
use Illuminate\Contracts\Pagination\CursorPaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Spatie\QueryBuilder\AllowedFilter;
use Spatie\QueryBuilder\AllowedSort;
use Spatie\QueryBuilder\QueryBuilder;

final readonly class ListZonesApi implements Action
{
    public function __construct(private ZoneApiResource $resource) {}

    /** @return CursorPaginator<int, array<string, mixed>> */
    public function handle(Request $request, int $perPage = 20): CursorPaginator
    {
        return QueryBuilder::for(Zone::query())
            ->allowedFilters([
                AllowedFilter::callback('search', function (Builder $query, mixed $value): void {
                    $term = trim((string) $value);
                    if ($term !== '') {
                        $query->where(fn (Builder $zone): Builder => $zone->where('name', 'like', "%{$term}%")->orWhere('code', 'like', "%{$term}%"));
                    }
                }),
            ])
            ->allowedSorts([AllowedSort::field('name'), AllowedSort::field('code'), AllowedSort::field('created_at')])
            ->defaultSort('name')
            ->with('parent')
            ->withCount('customers')
            ->cursorPaginate(min(max($perPage, 10), 100))
            ->withQueryString()
            ->through(fn (Zone $zone): array => $this->resource->make($zone));
    }
}
