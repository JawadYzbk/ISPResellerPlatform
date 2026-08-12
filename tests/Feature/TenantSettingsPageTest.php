<?php

use App\Models\ExchangeRate;
use App\Models\Tenant;
use App\Models\User;
use App\Models\WhatsAppAccount;
use App\Support\Tenancy;
use Database\Seeders\CapabilitySeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Http;

uses(RefreshDatabase::class);

it('renders and updates tenant settings through the owner surface', function (): void {
    $tenant = Tenant::create(['name' => 'Northline', 'slug' => 'northline', 'base_currency' => 'USD', 'collection_currency' => 'USD']);
    $user = User::create(['tenant_id' => $tenant->id, 'name' => 'Owner', 'email' => 'settings@example.test', 'password' => Hash::make('password'), 'role' => 'tenant_owner']);
    app(CapabilitySeeder::class)->run();
    app(Tenancy::class)->set($tenant);
    WhatsAppAccount::create([
        'label' => 'Primary WhatsApp',
        'job' => 'general',
        'bridge_id' => 'isp-manager',
        'status' => 'ready',
        'is_active' => true,
    ]);
    $user->assignRole('tenant_owner');
    $user->forceFill(['last_authenticated_at' => now()])->save();
    config()->set([
        'services.frankfurter.currency_catalog_enabled' => false,
        'services.whatsapp.mode' => 'web',
        'services.whatsapp.web.enabled' => true,
        'services.whatsapp.web.endpoint' => 'http://whatsapp-web:3001',
        'services.whatsapp.web.token' => 'bridge-token',
        'services.whatsapp.web.webhook_url' => 'http://app/api/v1/webhooks/gateways/whatsapp_web',
        'services.webhooks.secrets.whatsapp_web' => 'webhook-secret',
        'services.payments.driver' => 'null',
        'services.stripe.secret' => null,
        'services.stripe.publishable_key' => null,
        'services.stripe.endpoint' => null,
        'services.stripe.webhook_secret' => null,
        'services.whish.enabled' => false,
        'services.whish.channel' => null,
        'services.whish.secret' => null,
        'services.whish.website_url' => null,
    ]);
    Http::fake();

    $this->actingAs($user)
        ->get(route('settings.general'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('Settings/General')
            ->where('tenant.slug', 'northline')
            ->where('settings.locale', 'en')
            ->where('settings.collection_currency', 'USD')
            ->where('setup.logo_ready', false)
            ->where('setup.currency.rate_ready', true)
            ->where('setup.whatsapp.configured', true)
            ->where('setup.whatsapp.status', 'configured')
            ->where('payments.cash.ready', true)
            ->where('payments.stripe.status', 'not_selected')
            ->where('payments.whish.status', 'disabled')
        );
    Http::assertNothingSent();

    Http::fake([
        'http://whatsapp-web:3001/status' => Http::response(['status' => 'qr']),
    ]);
    app(Tenancy::class)->set($tenant);
    $this->actingAs($user)
        ->get(route('settings.readiness'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('Settings/Readiness')
            ->where('overall', 'FAIL')
            ->where('checks.0.name', 'Tenant status')
            ->where('checks.0.status', 'PASS')
            ->where('checks.9.name', 'Tenant logo')
            ->where('checks.9.status', 'WARN')
        );

    app(Tenancy::class)->set($tenant);
    $this->actingAs($user)
        ->put(route('settings.general.update'), [
            'name' => 'Northline Fiber',
            'locale' => 'ar',
            'timezone' => 'Asia/Beirut',
            'base_currency' => 'USD',
            'collection_currency' => 'LBP',
            'date_format' => 'd/m/Y',
            'time_format' => 'H:i',
            'rtl' => true,
            'grace_extends_period' => true,
            'notification_quiet_start' => '21:00',
            'notification_quiet_end' => '08:00',
            'resolved_ticket_auto_close_hours' => 96,
            'radius_interim_interval_seconds' => 600,
        ])
        ->assertRedirect(route('settings.general'));

    app(Tenancy::class)->set($tenant);
    expect($tenant->refresh()->name)->toBe('Northline Fiber')
        ->and($tenant->locale)->toBe('ar')
        ->and($tenant->collection_currency)->toBe('LBP')
        ->and($tenant->settingsData()->settings['grace_extends_period'])->toBeTrue()
        ->and($tenant->settingsData()->settings['resolved_ticket_auto_close_hours'])->toBe(96);

    $user->refresh();
    app(Tenancy::class)->set($tenant);
    $this->actingAs($user)
        ->get(route('dashboard'))
        ->assertInertia(fn ($page) => $page->where('app.locale', 'ar')->where('app.direction', 'rtl'));
});

it('does not expose tenant settings to a user without settings capability', function (): void {
    $tenant = Tenant::create(['name' => 'Northline', 'slug' => 'northline', 'base_currency' => 'USD', 'collection_currency' => 'USD']);
    $user = User::create(['tenant_id' => $tenant->id, 'name' => 'Cashier', 'email' => 'settings-cashier@example.test', 'password' => Hash::make('password'), 'role' => 'cashier']);
    app(CapabilitySeeder::class)->run();
    app(Tenancy::class)->set($tenant);
    $user->assignRole('cashier');

    $this->actingAs($user)->get(route('settings.general'))->assertForbidden();
});

it('does not present a stale collection rate as ready in workspace settings', function (): void {
    Config::set('services.fx.rate_max_age_hours', 24);
    $tenant = Tenant::create(['name' => 'Northline', 'slug' => 'northline', 'base_currency' => 'USD', 'collection_currency' => 'LBP']);
    $user = User::create(['tenant_id' => $tenant->id, 'name' => 'Owner', 'email' => 'settings-stale-rate@example.test', 'password' => Hash::make('password'), 'role' => 'tenant_owner']);
    app(CapabilitySeeder::class)->run();
    app(Tenancy::class)->run($tenant, function (): void {
        ExchangeRate::create([
            'base_currency' => 'USD',
            'quote_currency' => 'LBP',
            'rate_numerator' => 90_000,
            'rate_denominator' => 1,
            'effective_from' => now()->subDays(2),
            'source' => 'manual',
        ]);
    });
    app(Tenancy::class)->set($tenant);
    $user->assignRole('tenant_owner');
    $user->forceFill(['last_authenticated_at' => now()])->save();

    $this->actingAs($user)
        ->get(route('settings.general'))
        ->assertInertia(fn ($page) => $page
            ->component('Settings/General')
            ->where('setup.currency.rate_ready', false)
        );
});

it('uses the tenant RTL setting in shared app props for an English-speaking owner', function (): void {
    $tenant = Tenant::create(['name' => 'Northline', 'slug' => 'northline', 'base_currency' => 'USD', 'collection_currency' => 'USD']);
    $user = User::create([
        'tenant_id' => $tenant->id,
        'name' => 'Owner',
        'email' => 'settings-rtl@example.test',
        'password' => Hash::make('password'),
        'role' => 'tenant_owner',
        'locale' => 'en',
    ]);
    app(CapabilitySeeder::class)->run();
    app(Tenancy::class)->set($tenant);
    $user->assignRole('tenant_owner');
    $user->forceFill(['last_authenticated_at' => now()])->save();

    $this->actingAs($user)
        ->put(route('settings.general.update'), [
            'name' => 'Northline',
            'locale' => 'en',
            'timezone' => 'UTC',
            'base_currency' => 'USD',
            'collection_currency' => 'USD',
            'date_format' => 'Y-m-d',
            'time_format' => 'H:i',
            'rtl' => true,
            'grace_extends_period' => false,
            'notification_quiet_start' => '22:00',
            'notification_quiet_end' => '07:00',
            'resolved_ticket_auto_close_hours' => 72,
            'radius_interim_interval_seconds' => 300,
        ])
        ->assertRedirect(route('settings.general'));

    $tenant->refresh();
    $user->unsetRelation('tenant')->refresh();

    $this->actingAs($user)
        ->get(route('dashboard'))
        ->assertInertia(fn ($page) => $page->where('app.direction', 'rtl'));
});

it('returns to left-to-right shared app props when RTL is disabled', function (): void {
    $tenant = Tenant::create(['name' => 'Northline', 'slug' => 'northline', 'base_currency' => 'USD', 'collection_currency' => 'USD']);
    $user = User::create([
        'tenant_id' => $tenant->id,
        'name' => 'Owner',
        'email' => 'settings-rtl-reset@example.test',
        'password' => Hash::make('password'),
        'role' => 'tenant_owner',
        'locale' => 'en',
    ]);
    app(CapabilitySeeder::class)->run();
    app(Tenancy::class)->set($tenant);
    $user->assignRole('tenant_owner');
    $user->forceFill(['last_authenticated_at' => now()])->save();

    $settings = [
        'name' => 'Northline',
        'timezone' => 'UTC',
        'base_currency' => 'USD',
        'collection_currency' => 'USD',
        'date_format' => 'Y-m-d',
        'time_format' => 'H:i',
        'grace_extends_period' => false,
        'notification_quiet_start' => '22:00',
        'notification_quiet_end' => '07:00',
        'resolved_ticket_auto_close_hours' => 72,
        'radius_interim_interval_seconds' => 300,
    ];

    $this->actingAs($user)
        ->put(route('settings.general.update'), [...$settings, 'locale' => 'ar', 'rtl' => true])
        ->assertRedirect(route('settings.general'));

    $user->unsetRelation('tenant')->refresh();
    $this->actingAs($user)
        ->put(route('settings.general.update'), [...$settings, 'locale' => 'en', 'rtl' => false])
        ->assertRedirect(route('settings.general'));

    $user->unsetRelation('tenant')->refresh();
    $this->actingAs($user)
        ->get(route('dashboard'))
        ->assertInertia(fn ($page) => $page->where('app.direction', 'ltr'));
});
