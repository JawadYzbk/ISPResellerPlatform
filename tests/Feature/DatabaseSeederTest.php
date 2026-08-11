<?php

use App\Models\ExchangeRate;
use App\Models\Payment;
use App\Models\Tenant;
use App\Support\Tenancy;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;

uses(RefreshDatabase::class);

it('seeds the Lebanese demo tenant with an LBP collection path', function (): void {
    $this->seed(DatabaseSeeder::class);

    $tenant = Tenant::query()->where('slug', 'northline')->firstOrFail();
    app(Tenancy::class)->set($tenant);
    $rate = ExchangeRate::query()->where('base_currency', 'USD')->where('quote_currency', 'LBP')->firstOrFail();
    $payment = Payment::query()->where('currency', 'LBP')->firstOrFail();

    expect($tenant->base_currency)->toBe('USD')
        ->and($tenant->collection_currency)->toBe('LBP')
        ->and($tenant->logo_path)->toBe('tenants/'.$tenant->public_id.'/demo-logo.svg')
        ->and($rate->source)->toBe('demo')
        ->and($rate->rate_numerator)->toBe(90_000)
        ->and($payment->currency)->toBe('LBP')
        ->and($payment->ledger_currency)->toBe('USD')
        ->and($payment->base_amount)->toBeGreaterThan(0);

    Storage::disk((string) config('filesystems.default', 'local'))->assertExists($tenant->logo_path);
});
