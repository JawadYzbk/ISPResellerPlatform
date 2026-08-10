<?php

namespace App\Actions;

use App\Contracts\Action;
use App\Enums\PaymentStatus;
use App\Models\Payment;
use App\Models\User;
use Illuminate\Pagination\CursorPaginator;

final readonly class ListCollectorPayments implements Action
{
    /** @return CursorPaginator<int, Payment> */
    public function handle(User $user, ?string $date = null, int $perPage = 50): CursorPaginator
    {
        return Payment::query()
            ->with(['customer', 'invoice', 'allocations.invoice'])
            ->where('actor_id', $user->id)
            ->where('status', PaymentStatus::Posted)
            ->when($date !== null, fn ($query) => $query->whereDate('received_at', $date))
            ->latest('received_at')
            ->latest('id')
            ->cursorPaginate(min(max($perPage, 1), 100));
    }
}
