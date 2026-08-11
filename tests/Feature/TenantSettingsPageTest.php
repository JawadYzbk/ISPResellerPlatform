<?php

use App\Models\Tenant;
use App\Models\User;
use App\Support\Tenancy;
use Database\Seeders\CapabilitySeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;

uses(RefreshDatabase::class);

it('renders and updates tenant settings through the owner surface', function (): void {
    $tenant = Tenant::create(['name' => 'Northline', 'slug' => 'northline', 'base_currency' => 'USD', 'collection_currency' => 'USD']);
    $user = User::create(['tenant_id' => $tenant->id, 'name' => 'Owner', 'email' => 'settings@example.test', 'password' => Hash::make('password'), 'role' => 'tenant_owner']);
    app(CapabilitySeeder::class)->run();
    app(Tenancy::class)->set($tenant);
    $user->assignRole('tenant_owner');
    $user->forceFill(['last_authenticated_at' => now()])->save();

    $this->actingAs($user)
        ->get(route('settings.general'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('Settings/General')
            ->where('tenant.slug', 'northline')
            ->where('settings.locale', 'en')
            ->where('settings.collection_currency', 'USD')
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
});

it('does not expose tenant settings to a user without settings capability', function (): void {
    $tenant = Tenant::create(['name' => 'Northline', 'slug' => 'northline', 'base_currency' => 'USD', 'collection_currency' => 'USD']);
    $user = User::create(['tenant_id' => $tenant->id, 'name' => 'Cashier', 'email' => 'settings-cashier@example.test', 'password' => Hash::make('password'), 'role' => 'cashier']);
    app(CapabilitySeeder::class)->run();
    app(Tenancy::class)->set($tenant);
    $user->assignRole('cashier');

    $this->actingAs($user)->get(route('settings.general'))->assertForbidden();
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
