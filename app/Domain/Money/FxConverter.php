<?php

namespace App\Domain\Money;

use App\Models\ExchangeRate;
use App\Support\Money;
use Carbon\CarbonImmutable;
use DomainException;

final class FxConverter
{
    public function convert(Money $amount, string $targetCurrency, CarbonImmutable $at): Money
    {
        if ($amount->currency === $targetCurrency) {
            return $amount;
        }

        $rate = ExchangeRate::query()
            ->where('base_currency', $amount->currency)
            ->where('quote_currency', $targetCurrency)
            ->where('effective_from', '<=', $at)
            ->latest('effective_from')
            ->first();

        if (! $rate instanceof ExchangeRate) {
            throw new DomainException("No FX rate exists for {$amount->currency}/{$targetCurrency} at {$at->toIso8601String()}.");
        }

        $converted = intdiv(
            ($amount->amount * $rate->rate_numerator) + intdiv($rate->rate_denominator, 2),
            $rate->rate_denominator,
        );

        return new Money($converted, $targetCurrency);
    }
}
