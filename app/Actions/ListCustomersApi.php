<?php

namespace App\Actions;

use App\Contracts\Action;
use App\Models\Customer;
use Illuminate\Contracts\Pagination\CursorPaginator;
use Illuminate\Http\Request;
use Spatie\QueryBuilder\AllowedFilter;
use Spatie\QueryBuilder\AllowedSort;
use Spatie\QueryBuilder\QueryBuilder;

final readonly class ListCustomersApi implements Action
{
    /** @return CursorPaginator<int, Customer> */
    public function handle(Request $request, int $perPage = 20): CursorPaginator
    {
        return QueryBuilder::for(Customer::query())
            ->allowedFilters([AllowedFilter::scope('search'), AllowedFilter::exact('status')])
            ->allowedSorts([AllowedSort::field('created_at'), AllowedSort::field('code')])
            ->defaultSort('-created_at')
            ->with(['zone', 'services.plan'])
            ->cursorPaginate(min(max($perPage, 10), 100))
            ->withQueryString();
    }
}
