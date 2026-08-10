<?php

namespace App\Actions;

use App\Contracts\Action;
use App\Models\Ticket;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Pagination\LengthAwarePaginator;

final readonly class ListTickets implements Action
{
    /** @return LengthAwarePaginator<int, Ticket> */
    public function handle(?string $status, ?string $priority, ?string $search, int $perPage = 25): LengthAwarePaginator
    {
        return Ticket::query()
            ->with(['customer', 'service', 'assignee'])
            ->withCount('messages')
            ->when($status, fn (Builder $query) => $query->where('status', $status))
            ->when($priority, fn (Builder $query) => $query->where('priority', $priority))
            ->when($search, function (Builder $query) use ($search): void {
                $term = trim($search);
                $query->where(function (Builder $query) use ($term): void {
                    $query->where('number', 'like', "%{$term}%")
                        ->orWhere('subject', 'like', "%{$term}%")
                        ->orWhereHas('customer', fn (Builder $customer): Builder => $customer
                            ->where('code', 'like', "%{$term}%")
                            ->orWhere('first_name', 'like', "%{$term}%")
                            ->orWhere('last_name', 'like', "%{$term}%"));
                });
            })
            ->orderByRaw("case when status in ('open', 'in_progress', 'pending') then 0 else 1 end")
            ->orderBy('due_at')
            ->orderByDesc('created_at')
            ->paginate(min(max($perPage, 10), 100))
            ->withQueryString();
    }
}
