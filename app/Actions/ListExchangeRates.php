<?php

namespace App\Actions;

use App\Contracts\Action;
use App\Models\ExchangeRate;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Pagination\LengthAwarePaginator;

final readonly class ListExchangeRates implements Action
{
    /** @return LengthAwarePaginator<int, ExchangeRate> */
    public function handle(?string $baseCurrency, ?string $quoteCurrency, int $perPage = 25): LengthAwarePaginator
    {
        return ExchangeRate::query()
            ->when($baseCurrency, fn (Builder $query) => $query->where('base_currency', strtoupper($baseCurrency)))
            ->when($quoteCurrency, fn (Builder $query) => $query->where('quote_currency', strtoupper($quoteCurrency)))
            ->orderByDesc('effective_from')
            ->orderBy('base_currency')
            ->orderBy('quote_currency')
            ->paginate(min(max($perPage, 10), 100))
            ->withQueryString();
    }
}
