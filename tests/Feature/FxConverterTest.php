<?php

use App\Domain\Money\FxConverter;
use App\Enums\FxRoundingMode;
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

it('supports explicit flooring and ceiling without floating point arithmetic', function (): void {
    $tenant = Tenant::create(['name' => 'Northline', 'slug' => 'northline', 'base_currency' => 'USD', 'collection_currency' => 'LBP']);
    app(Tenancy::class)->set($tenant);
    ExchangeRate::create(['base_currency' => 'USD', 'quote_currency' => 'LBP', 'rate_numerator' => 1, 'rate_denominator' => 3, 'effective_from' => '2026-01-01', 'source' => 'frankfurter']);

    $converter = app(FxConverter::class);
    $at = CarbonImmutable::parse('2026-01-15');

    expect($converter->snapshot('USD', 'LBP', $at, roundingMode: FxRoundingMode::Floor->value)->convert(1))->toBe(0)
        ->and($converter->snapshot('USD', 'LBP', $at, roundingMode: FxRoundingMode::Ceil->value)->convert(1))->toBe(1)
        ->and($converter->snapshot('USD', 'LBP', $at, roundingMode: FxRoundingMode::Floor->value)->convert(-1))->toBe(-1)
        ->and($converter->snapshot('USD', 'LBP', $at, roundingMode: FxRoundingMode::Ceil->value)->convert(-1))->toBe(0);
});

it('keeps the effective date and provider source in the FX snapshot', function (): void {
    $tenant = Tenant::create(['name' => 'Northline', 'slug' => 'northline', 'base_currency' => 'USD', 'collection_currency' => 'LBP']);
    app(Tenancy::class)->set($tenant);
    ExchangeRate::create(['base_currency' => 'USD', 'quote_currency' => 'LBP', 'rate_numerator' => 90_000, 'rate_denominator' => 1, 'effective_from' => '2026-01-01', 'source' => 'frankfurter']);

    $snapshot = app(FxConverter::class)->snapshot('USD', 'LBP', CarbonImmutable::parse('2026-01-15'));

    expect($snapshot->rateSource)->toBe('frankfurter')
        ->and($snapshot->effectiveFrom?->toDateString())->toBe('2026-01-01');
});
