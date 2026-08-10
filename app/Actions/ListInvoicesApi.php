<?php

namespace App\Actions;

use App\Contracts\Action;
use App\Models\Invoice;
use App\Support\Api\InvoiceApiResource;
use Illuminate\Contracts\Pagination\CursorPaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Spatie\QueryBuilder\AllowedFilter;
use Spatie\QueryBuilder\AllowedSort;
use Spatie\QueryBuilder\QueryBuilder;

final readonly class ListInvoicesApi implements Action
{
    public function __construct(private InvoiceApiResource $resource) {}

    /** @return CursorPaginator<int, array<string, mixed>> */
    public function handle(Request $request, int $perPage = 20): CursorPaginator
    {
        return QueryBuilder::for(Invoice::query())
            ->allowedFilters([
                AllowedFilter::exact('status'),
                AllowedFilter::callback('search', function (Builder $query, mixed $value): void {
                    $term = trim((string) $value);
                    if ($term !== '') {
                        $query->where(function (Builder $invoice) use ($term): void {
                            $invoice->where('number', 'like', "%{$term}%")
                                ->orWhereHas('customer', fn (Builder $customer): Builder => $customer
                                    ->where('code', 'like', "%{$term}%")
                                    ->orWhere('first_name', 'like', "%{$term}%")
                                    ->orWhere('last_name', 'like', "%{$term}%"));
                        });
                    }
                }),
            ])
            ->allowedSorts([AllowedSort::field('created_at'), AllowedSort::field('issued_at'), AllowedSort::field('due_at'), AllowedSort::field('total_amount')])
            ->defaultSort('-created_at')
            ->with([
                'customer',
                'lines.plan',
                'lines.service',
                'payments.actor',
                'payments.allocations',
                'creditNotes.creator',
            ])
            ->cursorPaginate(min(max($perPage, 10), 100))
            ->withQueryString()
            ->through(fn (Invoice $invoice): array => $this->resource->make($invoice));
    }
}
