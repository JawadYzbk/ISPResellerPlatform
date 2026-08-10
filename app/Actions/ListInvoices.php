<?php

namespace App\Actions;

use App\Contracts\Action;
use App\Enums\PaymentStatus;
use App\Models\Invoice;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Pagination\LengthAwarePaginator;

final readonly class ListInvoices implements Action
{
    /** @return LengthAwarePaginator<int, Invoice> */
    public function handle(?string $status, ?string $search, int $perPage = 25): LengthAwarePaginator
    {
        return Invoice::query()
            ->with(['customer', 'payments' => fn ($query) => $query
                ->where('status', PaymentStatus::Posted)
                ->with('allocations'), 'creditNotes' => fn ($query) => $query->where('status', 'issued')])
            ->when($status, fn (Builder $query) => $query->where('status', $status))
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
            ->orderByDesc('created_at')
            ->paginate(min(max($perPage, 10), 100))
            ->withQueryString();
    }
}
