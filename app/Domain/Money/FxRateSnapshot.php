<?php

namespace App\Domain\Money;

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
    ) {
        if ($numerator < 1 || $denominator < 1) {
            throw new InvalidArgumentException('FX rate ratios must be positive integers.');
        }
    }

    public function convert(int $amount): int
    {
        if ($amount < 0) {
            throw new InvalidArgumentException('FX conversion amounts cannot be negative.');
        }

        return intdiv(($amount * $this->numerator) + intdiv($this->denominator, 2), $this->denominator);
    }

    /** @return array{source_currency: string, target_currency: string, numerator: int, denominator: int, source: string, overridden: bool} */
    public function toArray(): array
    {
        return [
            'source_currency' => $this->sourceCurrency,
            'target_currency' => $this->targetCurrency,
            'numerator' => $this->numerator,
            'denominator' => $this->denominator,
            'source' => $this->source,
            'overridden' => $this->overridden,
        ];
    }
}
