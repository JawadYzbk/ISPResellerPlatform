<?php

namespace App\Actions;

use App\Contracts\Action;
use App\Models\Service;
use App\Support\Api\ServiceApiResource;
use Illuminate\Contracts\Pagination\CursorPaginator;
use Illuminate\Http\Request;
use Spatie\QueryBuilder\AllowedFilter;
use Spatie\QueryBuilder\AllowedSort;
use Spatie\QueryBuilder\QueryBuilder;

final readonly class ListServicesApi implements Action
{
    public function __construct(private ServiceApiResource $resource) {}

    /** @return CursorPaginator<int, array<string, mixed>> */
    public function handle(Request $request, int $perPage = 20): CursorPaginator
    {
        return QueryBuilder::for(Service::query())
            ->allowedFilters([AllowedFilter::scope('search'), AllowedFilter::exact('status'), AllowedFilter::exact('network_state'), AllowedFilter::callback('expires_before', fn ($query, $value) => $query->where('expires_at', '<=', $value))])
            ->allowedSorts([AllowedSort::field('created_at'), AllowedSort::field('expires_at'), AllowedSort::field('username')])
            ->defaultSort('-created_at')
            ->with(['customer', 'plan', 'router.pop', 'events'])
            ->cursorPaginate(min(max($perPage, 10), 100))
            ->withQueryString()
            ->through(fn (Service $service): array => $this->resource->make($service));
    }
}
