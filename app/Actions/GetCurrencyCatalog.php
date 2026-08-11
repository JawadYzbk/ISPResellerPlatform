<?php

namespace App\Actions;

use App\Models\Currency;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;

final class GetCurrencyCatalog
{
    /** @var list<string> */
    private const PRIORITY_CODES = ['USD', 'EUR', 'LBP'];

    /** @return list<array{code: string, name: string, decimal_digits: int}> */
    public function handle(): array
    {
        $catalog = Cache::remember(
            $this->cacheKey(),
            now()->addDay(),
            fn (): array => $this->fetchCatalog(),
        );

        foreach (Currency::query()->where('is_active', true)->get(['code', 'name', 'decimal_digits']) as $currency) {
            $code = strtoupper((string) $currency->code);
            if ($code === '' || isset($catalog[$code])) {
                continue;
            }

            $catalog[$code] = [
                'code' => $code,
                'name' => (string) $currency->name,
                'decimal_digits' => (int) $currency->decimal_digits,
            ];
        }

        uasort($catalog, function (array $left, array $right): int {
            $leftPriority = array_search($left['code'], self::PRIORITY_CODES, true);
            $rightPriority = array_search($right['code'], self::PRIORITY_CODES, true);

            if ($leftPriority !== false || $rightPriority !== false) {
                return ($leftPriority === false ? PHP_INT_MAX : $leftPriority)
                    <=> ($rightPriority === false ? PHP_INT_MAX : $rightPriority);
            }

            return $left['code'] <=> $right['code'];
        });

        return array_values($catalog);
    }

    /** @return array<string, array{code: string, name: string, decimal_digits: int}> */
    private function fetchCatalog(): array
    {
        $fallback = $this->fallbackCatalog();
        $endpoint = rtrim((string) config('services.frankfurter.endpoint', 'https://api.frankfurter.dev'), '/');
        $url = str_ends_with($endpoint, '/v2') ? $endpoint.'/currencies' : $endpoint.'/v2/currencies';

        try {
            $response = Http::acceptJson()
                ->connectTimeout(2)
                ->timeout(max(1, (int) config('services.frankfurter.timeout', 10)))
                ->get($url);
        } catch (\Throwable) {
            return $fallback;
        }

        if (! $response->successful() || ! is_array($response->json())) {
            return $fallback;
        }

        $catalog = [];
        foreach ($response->json() as $item) {
            if (! is_array($item)) {
                continue;
            }

            $code = strtoupper(trim((string) ($item['iso_code'] ?? '')));
            $name = trim((string) ($item['name'] ?? ''));
            if (! preg_match('/^[A-Z]{3}$/', $code) || $name === '') {
                continue;
            }

            $catalog[$code] = [
                'code' => $code,
                'name' => $name,
                'decimal_digits' => $this->decimalDigits($code),
            ];
        }

        foreach ($fallback as $code => $currency) {
            $catalog[$code] ??= $currency;
        }

        return $catalog === [] ? $fallback : $catalog;
    }

    /** @return array<string, array{code: string, name: string, decimal_digits: int}> */
    private function fallbackCatalog(): array
    {
        return [
            'USD' => ['code' => 'USD', 'name' => 'United States Dollar', 'decimal_digits' => 2],
            'EUR' => ['code' => 'EUR', 'name' => 'Euro', 'decimal_digits' => 2],
            'LBP' => ['code' => 'LBP', 'name' => 'Lebanese Pound', 'decimal_digits' => 0],
        ];
    }

    private function cacheKey(): string
    {
        return 'currency-catalog:'.sha1((string) config('services.frankfurter.endpoint', 'https://api.frankfurter.dev'));
    }

    private function decimalDigits(string $code): int
    {
        return in_array($code, [
            'BIF', 'CLP', 'DJF', 'GNF', 'ISK', 'JPY', 'KMF', 'KRW', 'LBP', 'PYG', 'RWF', 'UGX', 'VND', 'VUV', 'XAF', 'XOF', 'XPF',
        ], true) ? 0 : 2;
    }
}
