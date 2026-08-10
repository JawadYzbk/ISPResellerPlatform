<?php

namespace App\Actions;

use App\Contracts\Action;
use App\Models\Router;
use App\Support\Api\RouterApiResource;
use Illuminate\Contracts\Pagination\CursorPaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Spatie\QueryBuilder\AllowedFilter;
use Spatie\QueryBuilder\AllowedSort;
use Spatie\QueryBuilder\QueryBuilder;

final readonly class ListRoutersApi implements Action
{
    public function __construct(private RouterApiResource $resource) {}

    /** @return CursorPaginator<int, array<string, mixed>> */
    public function handle(Request $request, int $perPage = 20): CursorPaginator
    {
        return QueryBuilder::for(Router::query())
            ->allowedFilters([
                AllowedFilter::exact('status'),
                AllowedFilter::callback('search', function (Builder $query, mixed $value): void {
                    $term = trim((string) $value);
                    if ($term !== '') {
                        $query->where(fn (Builder $router): Builder => $router->where('name', 'like', "%{$term}%")->orWhere('host', 'like', "%{$term}%"));
                    }
                }),
            ])
            ->allowedSorts([AllowedSort::field('created_at'), AllowedSort::field('name'), AllowedSort::field('last_seen_at'), AllowedSort::field('status')])
            ->defaultSort('name')
            ->with('pop')
            ->withCount('services')
            ->cursorPaginate(min(max($perPage, 10), 100))
            ->withQueryString()
            ->through(fn (Router $router): array => $this->resource->make($router));
    }
}
