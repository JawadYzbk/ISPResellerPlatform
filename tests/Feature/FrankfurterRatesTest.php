<?php

use App\Models\ExchangeRate;
use App\Models\Tenant;
use App\Support\Tenancy;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Http;

uses(RefreshDatabase::class);

it('imports exact Frankfurter ratios for USD and LBP and remains idempotent', function (): void {
    config([
        'services.frankfurter.enabled' => true,
        'services.frankfurter.quotes' => ['LBP', 'EUR'],
    ]);
    Http::fake([
        'https://api.frankfurter.dev/*' => Http::response([
            ['date' => '2026-08-10', 'base' => 'USD', 'quote' => 'LBP', 'rate' => 89500.5],
            ['date' => '2026-08-10', 'base' => 'USD', 'quote' => 'EUR', 'rate' => 0.9234],
        ]),
    ]);
    $tenant = Tenant::create(['name' => 'Northline', 'slug' => 'northline', 'base_currency' => 'USD', 'collection_currency' => 'LBP']);

    expect(Artisan::call('fx:sync-frankfurter'))->toBe(0)
        ->and(app(Tenancy::class)->set($tenant))->toBeNull()
        ->and(ExchangeRate::count())->toBe(2);

    $lbp = ExchangeRate::query()->where('quote_currency', 'LBP')->firstOrFail();
    $eur = ExchangeRate::query()->where('quote_currency', 'EUR')->firstOrFail();
    expect($lbp->rate_numerator)->toBe(179001)
        ->and($lbp->rate_denominator)->toBe(2)
        ->and($lbp->source)->toBe('frankfurter')
        ->and($lbp->metadata['provider_date'])->toBe('2026-08-10')
        ->and($eur->rate_numerator)->toBe(4617)
        ->and($eur->rate_denominator)->toBe(5000);

    expect(Artisan::call('fx:sync-frankfurter'))->toBe(0)
        ->and(ExchangeRate::count())->toBe(2);
});

it('keeps the last known rate when Frankfurter is unavailable', function (): void {
    config(['services.frankfurter.enabled' => true]);
    Http::fake(['https://api.frankfurter.dev/*' => Http::response(['message' => 'temporarily unavailable'], 503)]);
    $tenant = Tenant::create(['name' => 'Northline', 'slug' => 'northline', 'base_currency' => 'USD', 'collection_currency' => 'LBP']);
    app(Tenancy::class)->set($tenant);
    ExchangeRate::create(['base_currency' => 'USD', 'quote_currency' => 'LBP', 'rate_numerator' => 90_000, 'rate_denominator' => 1, 'effective_from' => now()->subDay(), 'source' => 'manual']);

    expect(Artisan::call('fx:sync-frankfurter'))->toBe(1)
        ->and(ExchangeRate::count())->toBe(1)
        ->and(ExchangeRate::firstOrFail()->source)->toBe('manual');
});
