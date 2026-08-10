<?php

namespace App\Actions;

use App\Contracts\Action;
use App\Models\CashShift;
use App\Models\User;
use Illuminate\Pagination\LengthAwarePaginator;

final readonly class ListCashShifts implements Action
{
    /** @return LengthAwarePaginator<int, CashShift> */
    public function handle(User $user, int $perPage = 20, bool $allCollectors = false): LengthAwarePaginator
    {
        return CashShift::query()
            ->when(! $allCollectors, fn ($query) => $query->where('user_id', $user->id))
            ->with('user')
            ->orderByDesc('opened_at')
            ->paginate(min(max($perPage, 10), 100))
            ->withQueryString();
    }
}
