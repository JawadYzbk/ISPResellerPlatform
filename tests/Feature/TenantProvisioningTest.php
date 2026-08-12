<?php

use App\Data\TenantSettings;
use App\Models\Branch;
use App\Models\Currency;
use App\Models\DocumentSequence;
use App\Models\MessageTemplate;
use App\Models\Tenant;
use App\Models\Zone;
use App\Support\MessageTemplateProvisioner;
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
        ->and(Currency::where('code', 'AED')->where('is_active', true)->exists())->toBeTrue()
        ->and(Currency::where('code', 'LBP')->exists())->toBeTrue()
        ->and(DocumentSequence::count())->toBe(7)
        ->and(DocumentSequence::where('key', 'work_order')->exists())->toBeTrue();
});

it('does not overwrite customized notification templates during reconciliation', function (): void {
    $tenant = Tenant::create([
        'name' => 'Template ISP',
        'slug' => 'template-isp',
        'base_currency' => 'USD',
        'collection_currency' => 'LBP',
    ]);

    app(Tenancy::class)->set($tenant);
    app(MessageTemplateProvisioner::class)->provision($tenant);
    $template = MessageTemplate::query()
        ->where('key', 'payment.receipt')
        ->where('channel', 'whatsapp')
        ->where('locale', 'en')
        ->firstOrFail();
    $template->update(['body' => 'Custom receipt copy.']);

    app(MessageTemplateProvisioner::class)->provision($tenant);

    expect($template->refresh()->body)->toBe('Custom receipt copy.');
});

it('provisions the active locale when a tenant changes language', function (): void {
    $tenant = Tenant::create([
        'name' => 'Locale ISP',
        'slug' => 'locale-isp',
        'base_currency' => 'USD',
        'collection_currency' => 'USD',
        'locale' => 'en',
    ]);

    app(Tenancy::class)->set($tenant);
    expect(MessageTemplate::where('locale', 'ar')->exists())->toBeFalse();

    $tenant->update(['locale' => 'ar']);
    app(Tenancy::class)->set($tenant);
    app(MessageTemplateProvisioner::class)->provision($tenant);

    expect(MessageTemplate::where('key', 'customer.welcome')->where('channel', 'whatsapp')->where('locale', 'ar')->exists())->toBeTrue();
});

it('marks a shared currency as both base and collection currency', function (): void {
    $tenant = Tenant::create([
        'name' => 'Single Currency ISP',
        'slug' => 'single-currency-isp',
        'base_currency' => 'USD',
        'collection_currency' => 'USD',
    ]);

    app(Tenancy::class)->set($tenant);
    $currency = Currency::where('code', 'USD')->firstOrFail();

    expect($currency->is_base)->toBeTrue()
        ->and($currency->is_collection)->toBeTrue()
        ->and($currency->is_active)->toBeTrue();
});

it('reconciles currency roles when tenant currency settings change', function (): void {
    $tenant = Tenant::create([
        'name' => 'Changing Currency ISP',
        'slug' => 'changing-currency-isp',
        'base_currency' => 'USD',
        'collection_currency' => 'USD',
    ]);

    $tenant->update(['collection_currency' => 'LBP']);
    app(Tenancy::class)->set($tenant);

    $usd = Currency::where('code', 'USD')->firstOrFail();
    $lbp = Currency::where('code', 'LBP')->firstOrFail();

    expect($usd->is_base)->toBeTrue()
        ->and($usd->is_collection)->toBeFalse()
        ->and($lbp->is_base)->toBeFalse()
        ->and($lbp->is_collection)->toBeTrue();

    $tenant->update(['collection_currency' => 'USD']);
    $usd->refresh();
    $lbp->refresh();

    expect($usd->is_base)->toBeTrue()
        ->and($usd->is_collection)->toBeTrue()
        ->and($lbp->is_collection)->toBeFalse();
});

it('provisions a newly selected Frankfurter currency for the tenant', function (): void {
    $tenant = Tenant::create([
        'name' => 'Expanded Currency ISP',
        'slug' => 'expanded-currency-isp',
        'base_currency' => 'USD',
        'collection_currency' => 'LBP',
    ]);

    $tenant->update(['base_currency' => 'JOD']);
    app(Tenancy::class)->set($tenant);

    $jod = Currency::where('code', 'JOD')->firstOrFail();

    expect($jod->is_active)->toBeTrue()
        ->and($jod->is_base)->toBeTrue()
        ->and($jod->is_collection)->toBeFalse()
        ->and(Currency::where('code', 'LBP')->where('is_collection', true)->exists())->toBeTrue();
});
