<?php

use App\Models\Tenant;
use App\Models\User;
use App\Support\Tenancy;
use Database\Seeders\CapabilitySeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

uses(RefreshDatabase::class);

function integrationOwner(): array
{
    $tenant = Tenant::create([
        'name' => 'Northline',
        'slug' => 'northline',
        'base_currency' => 'USD',
        'collection_currency' => 'LBP',
    ]);
    $user = User::create([
        'tenant_id' => $tenant->id,
        'name' => 'Owner',
        'email' => 'integration-owner-'.$tenant->id.'@example.test',
        'password' => Hash::make('password'),
        'role' => 'tenant_owner',
    ]);
    app(CapabilitySeeder::class)->run();
    app(Tenancy::class)->set($tenant);
    $user->assignRole('tenant_owner');
    $user->forceFill(['last_authenticated_at' => now()])->save();

    return [$tenant, $user];
}

it('renders tenant integration status without exposing provider secrets', function (): void {
    [$tenant, $user] = integrationOwner();
    $tenant->update(['provider_settings' => [
        'stripe.secret' => 'sk_test_private',
        'stripe.publishable_key' => 'pk_test_public',
        'whish.secret' => 'whish_private',
    ]]);

    $this->actingAs($user)
        ->get(route('settings.integrations'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('Settings/Integrations')
            ->where('configured.stripe_secret', true)
            ->where('configured.stripe_publishable_key', true)
            ->where('configured.whish_secret', true)
            ->where('sources.stripe_secret', 'workspace')
            ->missing('settings.stripe_secret')
            ->missing('settings.whish_secret')
        );
});

it('saves tenant integration settings and encrypts secret values', function (): void {
    [$tenant, $user] = integrationOwner();

    $this->actingAs($user)
        ->put(route('settings.integrations.update'), [
            'payment_driver' => 'stripe',
            'frankfurter_enabled' => true,
            'frankfurter_currency_catalog_enabled' => true,
            'frankfurter_endpoint' => 'https://api.frankfurter.dev',
            'frankfurter_connect_timeout' => 2,
            'frankfurter_timeout' => 10,
            'frankfurter_quotes' => 'LBP,USD,EUR',
            'whatsapp_mode' => 'web',
            'whatsapp_web_enabled' => true,
            'whatsapp_web_endpoint' => 'http://whatsapp-web:3001',
            'whatsapp_web_client_id' => 'northline',
            'whatsapp_web_webhook_url' => 'https://isp.example/api/whatsapp',
            'stripe_endpoint' => 'https://api.stripe.com',
            'stripe_webhook_tolerance' => 300,
            'stripe_timeout' => 15,
            'stripe_secret' => 'sk_test_private',
            'stripe_publishable_key' => 'pk_test_public',
            'stripe_webhook_secret' => 'whsec_private',
            'whish_enabled' => false,
            'whish_environment' => 'sandbox',
            'whish_website_url' => 'https://isp.example',
            'whish_endpoint' => 'https://whish.example/api',
            'whish_timeout' => 15,
            'whish_success_callback_url' => 'https://isp.example/whish/success',
            'whish_failure_callback_url' => 'https://isp.example/whish/failure',
            'whish_success_redirect_url' => 'https://isp.example/paid',
            'whish_failure_redirect_url' => 'https://isp.example/failed',
            'whatsapp_cloud_token' => '',
            'whatsapp_phone_number_id' => '',
            'whatsapp_web_token' => 'bridge_private',
            'whatsapp_webhook_secret' => 'webhook_private',
            'whish_channel' => 'merchant-channel',
            'whish_secret' => 'whish_private',
        ])
        ->assertRedirect(route('settings.integrations'));

    app(Tenancy::class)->set($tenant->refresh());
    expect(config('services.payments.driver'))->toBe('stripe')
        ->and(config('services.stripe.secret'))->toBe('sk_test_private')
        ->and(config('services.whatsapp.web.token'))->toBe('bridge_private')
        ->and(config('services.whish.channel'))->toBe('merchant-channel');

    $raw = (string) DB::table('tenants')->where('id', $tenant->id)->value('provider_settings');
    expect($raw)->not->toContain('sk_test_private');
});

it('denies integration settings to users without settings capability', function (): void {
    [$tenant, $user] = integrationOwner();
    $user->removeRole('tenant_owner');
    $user->assignRole('cashier');

    $this->actingAs($user)->get(route('settings.integrations'))->assertForbidden();
});
