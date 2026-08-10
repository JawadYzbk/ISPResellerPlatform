<?php

namespace App\Actions;

use App\Contracts\Action;
use App\Models\Payment;
use App\Support\Api\PaymentApiResource;
use Illuminate\Contracts\Pagination\CursorPaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Spatie\QueryBuilder\AllowedFilter;
use Spatie\QueryBuilder\AllowedSort;
use Spatie\QueryBuilder\QueryBuilder;

final readonly class ListPaymentsApi implements Action
{
    public function __construct(private PaymentApiResource $resource) {}

    /** @return CursorPaginator<int, array<string, mixed>> */
    public function handle(Request $request, int $perPage = 20): CursorPaginator
    {
        return QueryBuilder::for(Payment::query())
            ->allowedFilters([
                AllowedFilter::exact('status'),
                AllowedFilter::exact('method'),
                AllowedFilter::callback('search', function (Builder $query, mixed $value): void {
                    $term = trim((string) $value);
                    if ($term !== '') {
                        $query->where(function (Builder $payment) use ($term): void {
                            $payment->where('number', 'like', "%{$term}%")
                                ->orWhereHas('customer', fn (Builder $customer): Builder => $customer
                                    ->where('code', 'like', "%{$term}%")
                                    ->orWhere('first_name', 'like', "%{$term}%")
                                    ->orWhere('last_name', 'like', "%{$term}%"));
                        });
                    }
                }),
            ])
            ->allowedSorts([AllowedSort::field('received_at'), AllowedSort::field('amount'), AllowedSort::field('created_at')])
            ->defaultSort('-received_at')
            ->with(['customer', 'invoice', 'cashShift', 'actor', 'allocations.invoice'])
            ->cursorPaginate(min(max($perPage, 10), 100))
            ->withQueryString()
            ->through(fn (Payment $payment): array => $this->resource->make($payment));
    }
}
