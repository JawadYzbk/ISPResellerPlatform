<?php

namespace App\Actions;

use App\Contracts\Action;
use App\Domain\Money\ExchangeRateProvider;
use App\Models\ExchangeRate;
use App\Models\Tenant;
use App\Support\Tenancy;

final readonly class ImportFrankfurterExchangeRates implements Action
{
    public function __construct(private ExchangeRateProvider $provider, private Tenancy $tenancy) {}

    /** @param list<string> $quoteCurrencies */
    public function handle(Tenant $tenant, array $quoteCurrencies): int
    {
        return $this->tenancy->run($tenant, function () use ($tenant, $quoteCurrencies): int {
            $imported = 0;
            foreach ($this->provider->fetch($tenant->base_currency, $quoteCurrencies) as $quote) {
                $exists = ExchangeRate::query()
                    ->where('base_currency', $quote->baseCurrency)
                    ->where('quote_currency', $quote->quoteCurrency)
                    ->where('effective_from', $quote->effectiveFrom)
                    ->exists();
                if ($exists) {
                    continue;
                }

                ExchangeRate::create([
                    'base_currency' => $quote->baseCurrency,
                    'quote_currency' => $quote->quoteCurrency,
                    'rate_numerator' => $quote->numerator,
                    'rate_denominator' => $quote->denominator,
                    'effective_from' => $quote->effectiveFrom,
                    'source' => 'frankfurter',
                    'metadata' => [
                        'provider' => 'Frankfurter',
                        'provider_date' => $quote->effectiveFrom->toDateString(),
                        'rate_text' => $quote->rateText,
                    ],
                ]);
                $imported++;
            }

            return $imported;
        });
    }
}
