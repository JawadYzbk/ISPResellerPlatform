<?php

use App\Domain\Money\FxConverter;
use App\Models\ExchangeRate;
use App\Models\Tenant;
use App\Support\Money;
use App\Support\Tenancy;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('uses the rate effective at the historical instant', function (): void {
    $tenant = Tenant::create(['name' => 'Northline', 'slug' => 'northline', 'base_currency' => 'USD', 'collection_currency' => 'LBP']);
    app(Tenancy::class)->set($tenant);
    ExchangeRate::create(['base_currency' => 'USD', 'quote_currency' => 'LBP', 'rate_numerator' => 90_000, 'rate_denominator' => 1, 'effective_from' => '2026-01-01', 'source' => 'manual']);
    ExchangeRate::create(['base_currency' => 'USD', 'quote_currency' => 'LBP', 'rate_numerator' => 100_000, 'rate_denominator' => 1, 'effective_from' => '2026-02-01', 'source' => 'manual']);

    $converter = app(FxConverter::class);

    expect($converter->convert(new Money(100, 'USD'), 'LBP', CarbonImmutable::parse('2026-01-15'))->amount)->toBe(9_000_000)
        ->and($converter->convert(new Money(100, 'USD'), 'LBP', CarbonImmutable::parse('2026-02-15'))->amount)->toBe(10_000_000);
});

it('uses the inverse rate when converting collection currency to the ledger currency', function (): void {
    $tenant = Tenant::create(['name' => 'Northline', 'slug' => 'northline', 'base_currency' => 'USD', 'collection_currency' => 'LBP']);
    app(Tenancy::class)->set($tenant);
    ExchangeRate::create(['base_currency' => 'USD', 'quote_currency' => 'LBP', 'rate_numerator' => 90_000, 'rate_denominator' => 1, 'effective_from' => '2026-01-01', 'source' => 'manual']);

    $converter = app(FxConverter::class);

    expect($converter->convert(new Money(9_000_000, 'LBP'), 'USD', CarbonImmutable::parse('2026-01-15'))->amount)->toBe(100);
});
