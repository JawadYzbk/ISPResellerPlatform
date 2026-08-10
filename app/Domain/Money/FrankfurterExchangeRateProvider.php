<?php

namespace App\Domain\Money;

use Carbon\CarbonImmutable;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;
use InvalidArgumentException;
use RuntimeException;

final class FrankfurterExchangeRateProvider implements ExchangeRateProvider
{
    /** @param list<string> $quoteCurrencies @return list<ExchangeRateQuote> */
    public function fetch(string $baseCurrency, array $quoteCurrencies): array
    {
        $baseCurrency = $this->currency($baseCurrency);
        $quotes = array_values(array_unique(array_filter(array_map(fn (string $currency): string => $this->currency($currency), $quoteCurrencies), fn (string $currency): bool => $currency !== $baseCurrency)));
        if ($quotes === []) {
            return [];
        }

        try {
            $response = Http::acceptJson()
                ->timeout((int) config('services.frankfurter.timeout', 10))
                ->retry(2, 200)
                ->get(rtrim((string) config('services.frankfurter.endpoint', 'https://api.frankfurter.dev'), '/').'/v2/rates', [
                    'base' => $baseCurrency,
                    'quotes' => implode(',', $quotes),
                ]);
        } catch (ConnectionException $exception) {
            throw new RuntimeException('Frankfurter is unreachable: '.$exception->getMessage(), previous: $exception);
        }

        if ($response->failed()) {
            throw new RuntimeException('Frankfurter rejected the rate request with HTTP '.$response->status().'.');
        }

        $rows = json_decode($response->body(), true, 512, JSON_THROW_ON_ERROR);
        if (! is_array($rows)) {
            throw new RuntimeException('Frankfurter returned an invalid rate response.');
        }

        $result = [];
        foreach ($rows as $row) {
            if (! is_array($row) || ! is_string($row['date'] ?? null) || ! is_string($row['base'] ?? null) || ! is_string($row['quote'] ?? null) || (! is_int($row['rate'] ?? null) && ! is_float($row['rate'] ?? null) && ! is_string($row['rate'] ?? null))) {
                throw new RuntimeException('Frankfurter returned a malformed rate row.');
            }

            $quote = $this->currency($row['quote']);
            if ($this->currency($row['base']) !== $baseCurrency || ! in_array($quote, $quotes, true)) {
                continue;
            }
            $rateText = is_string($row['rate']) ? $row['rate'] : (string) $row['rate'];
            [$numerator, $denominator] = $this->ratio($rateText);
            $result[] = new ExchangeRateQuote($baseCurrency, $quote, $numerator, $denominator, CarbonImmutable::parse($row['date'])->startOfDay(), $rateText);
        }

        return $result;
    }

    private function currency(string $currency): string
    {
        $currency = strtoupper(trim($currency));
        if (preg_match('/^[A-Z]{3}$/', $currency) !== 1) {
            throw new InvalidArgumentException('Currency must be a three-letter ISO code.');
        }

        return $currency;
    }

    /** @return array{0: int, 1: int} */
    private function ratio(string $value): array
    {
        $value = trim($value);
        if (preg_match('/^\+?(?<whole>\d+)(?:\.(?<fraction>\d+))?(?:[eE](?<exponent>[+-]?\d+))?$/', $value, $matches) !== 1) {
            throw new RuntimeException('Frankfurter returned a non-decimal rate.');
        }

        $digits = ltrim($matches['whole'].($matches['fraction'] ?? ''), '0') ?: '0';
        $scale = strlen($matches['fraction'] ?? '') - (int) ($matches['exponent'] ?? 0);
        if ($scale < 0) {
            $digits .= str_repeat('0', -$scale);
            $scale = 0;
        }
        if ($scale > 12) {
            throw new RuntimeException('Frankfurter returned a rate with excessive precision.');
        }

        $numerator = (int) $digits;
        $denominator = 10 ** $scale;
        $divisor = $this->gcd($numerator, $denominator);

        return [intdiv($numerator, $divisor), intdiv($denominator, $divisor)];
    }

    private function gcd(int $left, int $right): int
    {
        while ($right !== 0) {
            [$left, $right] = [$right, $left % $right];
        }

        return max(1, $left);
    }
}
