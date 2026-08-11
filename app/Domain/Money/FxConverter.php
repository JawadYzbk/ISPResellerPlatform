<?php

namespace App\Domain\Money;

use App\Enums\FxRoundingMode;
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
        ?string $roundingMode = null,
        bool $requireFresh = false,
    ): FxRateSnapshot {
        $rounding = $this->roundingMode($roundingMode);

        if ($sourceCurrency === $targetCurrency) {
            return new FxRateSnapshot($sourceCurrency, $targetCurrency, 1, 1, 'identity', false, $rounding);
        }

        if (($overrideNumerator === null) xor ($overrideDenominator === null)) {
            throw new DomainException('Both FX override ratio values are required.');
        }
        if ($overrideNumerator !== null && $overrideDenominator !== null) {
            return new FxRateSnapshot($sourceCurrency, $targetCurrency, $overrideNumerator, $overrideDenominator, 'operator_override', true, $rounding, $at, 'operator_override');
        }

        $direct = ExchangeRate::query()
            ->where('base_currency', $sourceCurrency)
            ->where('quote_currency', $targetCurrency)
            ->where('effective_from', '<=', $at)
            ->latest('effective_from')
            ->first();
        if ($direct instanceof ExchangeRate) {
            $this->assertFresh($direct, $at, $requireFresh);

            return new FxRateSnapshot($sourceCurrency, $targetCurrency, $direct->rate_numerator, $direct->rate_denominator, 'rate:'.$direct->id, false, $rounding, CarbonImmutable::instance($direct->effective_from), $direct->source);
        }

        $inverse = ExchangeRate::query()
            ->where('base_currency', $targetCurrency)
            ->where('quote_currency', $sourceCurrency)
            ->where('effective_from', '<=', $at)
            ->latest('effective_from')
            ->first();
        if ($inverse instanceof ExchangeRate) {
            $this->assertFresh($inverse, $at, $requireFresh);

            return new FxRateSnapshot($sourceCurrency, $targetCurrency, $inverse->rate_denominator, $inverse->rate_numerator, 'rate:'.$inverse->id.':inverse', false, $rounding, CarbonImmutable::instance($inverse->effective_from), $inverse->source);
        }

        throw new DomainException("No FX rate exists for {$sourceCurrency}/{$targetCurrency} at {$at->toIso8601String()}.");
    }

    private function assertFresh(ExchangeRate $rate, CarbonImmutable $at, bool $required): void
    {
        if (! $required) {
            return;
        }

        $effectiveFrom = $rate->effective_from;
        $ageHours = $effectiveFrom === null ? PHP_INT_MAX : (int) CarbonImmutable::instance($effectiveFrom)->diffInHours($at);
        $maxAgeHours = max(1, (int) config('services.fx.rate_max_age_hours', 72));
        if ($ageHours > $maxAgeHours) {
            throw new DomainException("The FX rate for {$rate->base_currency}/{$rate->quote_currency} is {$ageHours} hour(s) old; refresh it or provide an approved override.");
        }
    }

    private function roundingMode(?string $roundingMode): FxRoundingMode
    {
        $value = $roundingMode ?: (string) config('services.fx.rounding_mode', FxRoundingMode::HalfUp->value);
        $mode = FxRoundingMode::tryFrom($value);

        return $mode ?? throw new DomainException("Unsupported FX rounding mode: {$value}.");
    }
}
