<?php

namespace App\Actions;

use App\Contracts\Action;
use App\Models\Customer;
use App\Support\Api\CustomerApiResource;
use Illuminate\Contracts\Pagination\CursorPaginator;
use Illuminate\Http\Request;
use Spatie\QueryBuilder\AllowedFilter;
use Spatie\QueryBuilder\AllowedSort;
use Spatie\QueryBuilder\QueryBuilder;

final readonly class ListCustomersApi implements Action
{
    public function __construct(private CustomerApiResource $resource) {}

    /** @return CursorPaginator<int, array<string, mixed>> */
    public function handle(Request $request, int $perPage = 20): CursorPaginator
    {
        return QueryBuilder::for(Customer::query())
            ->allowedFilters([AllowedFilter::scope('search'), AllowedFilter::exact('status')])
            ->allowedSorts([AllowedSort::field('created_at'), AllowedSort::field('code')])
            ->defaultSort('-created_at')
            ->with(['zone', 'services.plan', 'services.router', 'services.events'])
            ->cursorPaginate(min(max($perPage, 10), 100))
            ->withQueryString()
            ->through(fn (Customer $customer): array => $this->resource->make($customer));
    }
}
