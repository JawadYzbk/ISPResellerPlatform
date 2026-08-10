<?php

namespace App\Actions;

use App\Contracts\Action;
use App\Models\Partner;
use App\Models\Plan;
use App\Models\PriceBookItem;
use Carbon\CarbonInterface;
use DomainException;

final readonly class ResolvePartnerPrice implements Action
{
    public function handle(Partner $partner, Plan $plan, string $currency, ?CarbonInterface $at = null): PriceBookItem
    {
        if ($partner->tenant_id !== $plan->tenant_id) {
            throw new DomainException('Partner and plan must belong to the same tenant.');
        }

        $at ??= now();
        $currency = strtoupper($currency);

        return PriceBookItem::query()
            ->select('price_book_items.*')
            ->join('price_books', 'price_books.id', '=', 'price_book_items.price_book_id')
            ->where('price_book_items.plan_id', $plan->id)
            ->where('price_book_items.currency', $currency)
            ->where('price_book_items.effective_from', '<=', $at)
            ->where(fn ($query) => $query->whereNull('price_book_items.effective_to')->orWhere('price_book_items.effective_to', '>', $at))
            ->where('price_books.status', 'active')
            ->where('price_books.effective_from', '<=', $at)
            ->where(fn ($query) => $query->whereNull('price_books.effective_to')->orWhere('price_books.effective_to', '>', $at))
            ->where(fn ($query) => $query->where('price_books.partner_id', $partner->id)->orWhereNull('price_books.partner_id'))
            ->orderByRaw('CASE WHEN price_books.partner_id = ? THEN 0 ELSE 1 END', [$partner->id])
            ->orderByDesc('price_books.effective_from')
            ->orderByDesc('price_book_items.effective_from')
            ->with(['priceBook', 'commissionRule'])
            ->firstOrFail();
    }
}
