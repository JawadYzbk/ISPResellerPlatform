<?php

namespace App\Actions;

use App\Contracts\Action;
use App\Models\CreditNote;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Pagination\LengthAwarePaginator;

final readonly class ListCreditNotes implements Action
{
    /** @return LengthAwarePaginator<int, CreditNote> */
    public function handle(?string $search, int $perPage = 25): LengthAwarePaginator
    {
        return CreditNote::query()
            ->with(['invoice', 'customer', 'creator'])
            ->when($search, function (Builder $query) use ($search): void {
                $term = trim($search);
                $query->where(function (Builder $query) use ($term): void {
                    $query->where('number', 'like', "%{$term}%")
                        ->orWhere('reason', 'like', "%{$term}%")
                        ->orWhereHas('invoice', fn (Builder $invoice): Builder => $invoice->where('number', 'like', "%{$term}%"))
                        ->orWhereHas('customer', fn (Builder $customer): Builder => $customer
                            ->where('code', 'like', "%{$term}%")
                            ->orWhere('first_name', 'like', "%{$term}%")
                            ->orWhere('last_name', 'like', "%{$term}%"));
                });
            })
            ->orderByDesc('issued_at')
            ->paginate(min(max($perPage, 10), 100))
            ->withQueryString();
    }
}
