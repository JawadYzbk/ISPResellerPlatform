<?php

namespace App\Domain\Money;

interface ExchangeRateProvider
{
    /** @param list<string> $quoteCurrencies @return list<ExchangeRateQuote> */
    public function fetch(string $baseCurrency, array $quoteCurrencies): array;
}
