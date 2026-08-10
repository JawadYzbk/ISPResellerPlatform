<?php

namespace App\Domain\Money;

use Carbon\CarbonImmutable;

final readonly class ExchangeRateQuote
{
    public function __construct(
        public string $baseCurrency,
        public string $quoteCurrency,
        public int $numerator,
        public int $denominator,
        public CarbonImmutable $effectiveFrom,
        public string $rateText,
    ) {}
}
