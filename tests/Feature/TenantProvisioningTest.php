<?php

use App\Data\TenantSettings;
use App\Models\Branch;
use App\Models\Currency;
use App\Models\DocumentSequence;
use App\Models\Tenant;
use App\Models\Zone;
use App\Support\Tenancy;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('provisions the operational defaults for a new tenant', function (): void {
    $tenant = Tenant::create([
        'name' => 'Provisioned ISP',
        'slug' => 'provisioned-isp',
        'base_currency' => 'USD',
        'collection_currency' => 'LBP',
        'locale' => 'ar',
        'timezone' => 'Asia/Beirut',
    ]);

    expect($tenant->settingsData())->toBeInstanceOf(TenantSettings::class)
        ->and($tenant->settingsData()->rtl)->toBeTrue();

    app(Tenancy::class)->set($tenant);

    expect(Branch::where('is_default', true)->count())->toBe(1)
        ->and(Zone::where('code', 'DEFAULT')->exists())->toBeTrue()
        ->and(Currency::where('code', 'USD')->exists())->toBeTrue()
        ->and(Currency::where('code', 'LBP')->exists())->toBeTrue()
        ->and(DocumentSequence::count())->toBe(5);
});
