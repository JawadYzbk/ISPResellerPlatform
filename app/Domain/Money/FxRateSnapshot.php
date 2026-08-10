<?php

namespace App\Domain\Money;

use App\Enums\FxRoundingMode;
use Carbon\CarbonImmutable;
use InvalidArgumentException;

final readonly class FxRateSnapshot
{
    public function __construct(
        public string $sourceCurrency,
        public string $targetCurrency,
        public int $numerator,
        public int $denominator,
        public string $source,
        public bool $overridden = false,
        public FxRoundingMode $roundingMode = FxRoundingMode::HalfUp,
        public ?CarbonImmutable $effectiveFrom = null,
        public ?string $rateSource = null,
    ) {
        if ($numerator < 1 || $denominator < 1) {
            throw new InvalidArgumentException('FX rate ratios must be positive integers.');
        }
    }

    public function convert(int $amount, ?FxRoundingMode $roundingMode = null): int
    {
        $mode = $roundingMode ?? $this->roundingMode;
        $product = $amount * $this->numerator;
        $negative = $product < 0;
        $absolute = abs($product);
        $quotient = intdiv($absolute, $this->denominator);
        $remainder = $absolute % $this->denominator;

        $rounded = match ($mode) {
            FxRoundingMode::Floor => $negative
                ? $quotient + ($remainder > 0 ? 1 : 0)
                : $quotient,
            FxRoundingMode::Ceil => $negative
                ? $quotient
                : $quotient + ($remainder > 0 ? 1 : 0),
            FxRoundingMode::HalfUp => $quotient + ($remainder * 2 >= $this->denominator ? 1 : 0),
        };

        return $negative ? -$rounded : $rounded;
    }

    /** @return array{source_currency: string, target_currency: string, numerator: int, denominator: int, source: string, overridden: bool, rounding_mode: string, effective_from: string|null, rate_source: string|null} */
    public function toArray(): array
    {
        return [
            'source_currency' => $this->sourceCurrency,
            'target_currency' => $this->targetCurrency,
            'numerator' => $this->numerator,
            'denominator' => $this->denominator,
            'source' => $this->source,
            'overridden' => $this->overridden,
            'rounding_mode' => $this->roundingMode->value,
            'effective_from' => $this->effectiveFrom?->toIso8601String(),
            'rate_source' => $this->rateSource,
        ];
    }
}
