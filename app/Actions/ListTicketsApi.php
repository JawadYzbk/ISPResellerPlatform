<?php

namespace App\Actions;

use App\Contracts\Action;
use App\Models\Ticket;
use App\Support\Api\TicketApiResource;
use Illuminate\Contracts\Pagination\CursorPaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Spatie\QueryBuilder\AllowedFilter;
use Spatie\QueryBuilder\AllowedSort;
use Spatie\QueryBuilder\QueryBuilder;

final readonly class ListTicketsApi implements Action
{
    public function __construct(private TicketApiResource $resource) {}

    /** @return CursorPaginator<int, array<string, mixed>> */
    public function handle(Request $request, int $perPage = 20): CursorPaginator
    {
        return QueryBuilder::for(Ticket::query())
            ->allowedFilters([
                AllowedFilter::exact('status'),
                AllowedFilter::exact('priority'),
                AllowedFilter::callback('search', function (Builder $query, mixed $value): void {
                    $term = trim((string) $value);
                    if ($term !== '') {
                        $query->where(function (Builder $ticket) use ($term): void {
                            $ticket->where('number', 'like', "%{$term}%")
                                ->orWhere('subject', 'like', "%{$term}%")
                                ->orWhereHas('customer', fn (Builder $customer): Builder => $customer
                                    ->where('code', 'like', "%{$term}%")
                                    ->orWhere('first_name', 'like', "%{$term}%")
                                    ->orWhere('last_name', 'like', "%{$term}%"));
                        });
                    }
                }),
            ])
            ->allowedSorts([AllowedSort::field('created_at'), AllowedSort::field('due_at'), AllowedSort::field('priority'), AllowedSort::field('status')])
            ->defaultSort('-created_at')
            ->with(['customer', 'service', 'assignee'])
            ->withCount('messages')
            ->cursorPaginate(min(max($perPage, 10), 100))
            ->withQueryString()
            ->through(fn (Ticket $ticket): array => $this->resource->make($ticket, false));
    }
}
