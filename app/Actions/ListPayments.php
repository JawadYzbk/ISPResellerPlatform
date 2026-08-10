<?php

namespace App\Actions;

use App\Contracts\Action;
use App\Models\Payment;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Pagination\LengthAwarePaginator;

final readonly class ListPayments implements Action
{
    /** @return LengthAwarePaginator<int, Payment> */
    public function handle(?string $status, ?string $method, ?string $search, int $perPage = 25): LengthAwarePaginator
    {
        return Payment::query()
            ->with(['customer', 'invoice', 'actor'])
            ->when($status, fn (Builder $query) => $query->where('status', $status))
            ->when($method, fn (Builder $query) => $query->where('method', $method))
            ->when($search, function (Builder $query) use ($search): void {
                $term = trim($search);
                $query->where(function (Builder $query) use ($term): void {
                    $query->where('number', 'like', "%{$term}%")
                        ->orWhereHas('customer', fn (Builder $customer): Builder => $customer
                            ->where('code', 'like', "%{$term}%")
                            ->orWhere('first_name', 'like', "%{$term}%")
                            ->orWhere('last_name', 'like', "%{$term}%"));
                });
            })
            ->orderByDesc('received_at')
            ->orderByDesc('id')
            ->paginate(min(max($perPage, 10), 100))
            ->withQueryString();
    }
}
