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
        return new Money($this->snapshot($amount->currency, $targetCurrency, $at)->convert($amount->amount), $targetCurrency);
    }

    public function snapshot(
        string $sourceCurrency,
        string $targetCurrency,
        CarbonImmutable $at,
        ?int $overrideNumerator = null,
        ?int $overrideDenominator = null,
    ): FxRateSnapshot {
        if ($sourceCurrency === $targetCurrency) {
            return new FxRateSnapshot($sourceCurrency, $targetCurrency, 1, 1, 'identity');
        }

        if (($overrideNumerator === null) xor ($overrideDenominator === null)) {
            throw new DomainException('Both FX override ratio values are required.');
        }
        if ($overrideNumerator !== null && $overrideDenominator !== null) {
            return new FxRateSnapshot($sourceCurrency, $targetCurrency, $overrideNumerator, $overrideDenominator, 'operator_override', true);
        }

        $direct = ExchangeRate::query()
            ->where('base_currency', $sourceCurrency)
            ->where('quote_currency', $targetCurrency)
            ->where('effective_from', '<=', $at)
            ->latest('effective_from')
            ->first();
        if ($direct instanceof ExchangeRate) {
            return new FxRateSnapshot($sourceCurrency, $targetCurrency, $direct->rate_numerator, $direct->rate_denominator, 'rate:'.$direct->id);
        }

        $inverse = ExchangeRate::query()
            ->where('base_currency', $targetCurrency)
            ->where('quote_currency', $sourceCurrency)
            ->where('effective_from', '<=', $at)
            ->latest('effective_from')
            ->first();
        if ($inverse instanceof ExchangeRate) {
            return new FxRateSnapshot($sourceCurrency, $targetCurrency, $inverse->rate_denominator, $inverse->rate_numerator, 'rate:'.$inverse->id.':inverse');
        }

        throw new DomainException("No FX rate exists for {$sourceCurrency}/{$targetCurrency} at {$at->toIso8601String()}.");
    }
}
