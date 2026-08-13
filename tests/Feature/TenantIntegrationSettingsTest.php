<?php

use App\Models\Tenant;
use App\Support\Tenancy;
use App\Support\TenantIntegrationSettings;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;

uses(RefreshDatabase::class);

it('applies encrypted tenant integration settings while retaining environment fallbacks', function (): void {
    $tenant = Tenant::factory()->create([
        'provider_settings' => [
            'payments.driver' => 'stripe',
            'stripe.secret' => 'sk_test_tenant',
            'stripe.publishable_key' => 'pk_test_tenant',
            'whish.enabled' => true,
        ],
    ]);

    app(Tenancy::class)->set($tenant);

    expect(config('services.payments.driver'))->toBe('stripe')
        ->and(config('services.stripe.secret'))->toBe('sk_test_tenant')
        ->and(config('services.stripe.publishable_key'))->toBe('pk_test_tenant')
        ->and(config('services.whish.enabled'))->toBeTrue()
        ->and(config('services.stripe.endpoint'))->toBe('https://api.stripe.com');

    $raw = (string) DB::table('tenants')->where('id', $tenant->id)->value('provider_settings');
    expect($raw)->not->toContain('sk_test_tenant');

    app(Tenancy::class)->clear();

    expect(config('services.payments.driver'))->not->toBe('stripe')
        ->and(config('services.stripe.secret'))->not->toBe('sk_test_tenant');
});

it('does not apply provider settings from another tenant', function (): void {
    $first = Tenant::factory()->create(['provider_settings' => ['stripe.secret' => 'sk_test_first']]);
    $second = Tenant::factory()->create(['provider_settings' => ['stripe.secret' => 'sk_test_second']]);

    app(Tenancy::class)->set($first);
    expect(config('services.stripe.secret'))->toBe('sk_test_first');

    app(Tenancy::class)->set($second);
    expect(config('services.stripe.secret'))->toBe('sk_test_second');

    app(Tenancy::class)->clear();
});

it('filters unknown provider setting keys before applying them', function (): void {
    $tenant = Tenant::factory()->create([
        'provider_settings' => [
            'stripe.secret' => 'sk_test_valid',
            'app.key' => 'base64:must-not-be-applied',
        ],
    ]);

    app(TenantIntegrationSettings::class)->apply($tenant);

    expect(config('services.stripe.secret'))->toBe('sk_test_valid')
        ->and(config('app.key'))->not->toBe('base64:must-not-be-applied');
});
