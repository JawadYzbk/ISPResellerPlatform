<?php

use App\Actions\GetCurrencyCatalog;
use App\Models\Tenant;
use App\Support\Tenancy;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;

uses(RefreshDatabase::class);

it('loads the Frankfurter currency catalog with Lebanon currencies first', function (): void {
    $tenant = Tenant::factory()->create();
    app(Tenancy::class)->set($tenant);
    config()->set('services.frankfurter.currency_catalog_enabled', true);
    Cache::forget('currency-catalog:'.sha1((string) config('services.frankfurter.endpoint')));
    Http::fake([
        'https://api.frankfurter.dev/v2/currencies' => Http::response([
            ['iso_code' => 'GBP', 'name' => 'Pound Sterling'],
            ['iso_code' => 'LBP', 'name' => 'Lebanese Pound'],
            ['iso_code' => 'EUR', 'name' => 'Euro'],
            ['iso_code' => 'USD', 'name' => 'United States Dollar'],
        ]),
    ]);

    $catalog = app(GetCurrencyCatalog::class)->handle();

    expect(array_slice(array_column($catalog, 'code'), 0, 4))->toBe(['USD', 'LBP', 'EUR', 'AED'])
        ->and($catalog[0]['name'])->toBe('United States Dollar')
        ->and($catalog[1]['decimal_digits'])->toBe(0)
        ->and($catalog[3]['name'])->toBe('United Arab Emirates Dirham');

    Http::assertSent(fn ($request): bool => $request->url() === 'https://api.frankfurter.dev/v2/currencies');
});

it('serves the refreshed catalog when the cache cannot store its currency names', function (): void {
    $tenant = Tenant::factory()->create();
    app(Tenancy::class)->set($tenant);
    config()->set('services.frankfurter.currency_catalog_enabled', true);
    Http::fake([
        'https://api.frankfurter.dev/v2/currencies' => Http::response([
            ['iso_code' => 'PLN', 'name' => 'Polish Złoty'],
        ]),
    ]);

    Cache::shouldReceive('get')->once()->andThrow(new RuntimeException('cache encoding failure'));
    Cache::shouldReceive('put')->once()->andThrow(new RuntimeException('cache encoding failure'));

    $catalog = app(GetCurrencyCatalog::class)->handle();

    expect(collect($catalog)->firstWhere('code', 'PLN')['name'] ?? null)->toBe('Polish Złoty');
});
