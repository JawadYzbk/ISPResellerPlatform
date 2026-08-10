<?php

use App\Models\ExchangeRate;
use App\Models\Tenant;
use App\Models\User;
use App\Support\Tenancy;
use Database\Seeders\CapabilitySeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;

uses(RefreshDatabase::class);

it('lists and creates effective-dated exchange rates for a tenant', function (): void {
    $tenant = Tenant::create(['name' => 'Northline', 'slug' => 'northline', 'base_currency' => 'USD', 'collection_currency' => 'USD']);
    $user = User::create([
        'tenant_id' => $tenant->id,
        'name' => 'Workspace owner',
        'email' => 'rates@example.test',
        'password' => Hash::make('password'),
        'role' => 'tenant_owner',
    ]);
    app(CapabilitySeeder::class)->run();
    app(Tenancy::class)->set($tenant);
    $user->assignRole('tenant_owner');
    $user->forceFill(['last_authenticated_at' => now()])->save();

    ExchangeRate::create([
        'base_currency' => 'USD',
        'quote_currency' => 'LBP',
        'rate_numerator' => 90000,
        'rate_denominator' => 1,
        'effective_from' => '2026-01-01',
        'source' => 'manual opening rate',
    ]);

    $this->actingAs($user)
        ->get(route('billing.exchange-rates', ['base_currency' => 'USD']))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('Billing/ExchangeRates')
            ->where('rates.data.0.base_currency', 'USD')
            ->where('rates.data.0.quote_currency', 'LBP')
            ->where('filters.base_currency', 'USD')
        );

    app(Tenancy::class)->set($tenant);
    $this->actingAs($user)
        ->post(route('billing.exchange-rates.store'), [
            'base_currency' => 'usd',
            'quote_currency' => 'eur',
            'rate_numerator' => 91,
            'rate_denominator' => 100,
            'effective_from' => '2026-02-01 00:00:00',
            'source' => 'treasury desk',
        ])
        ->assertRedirect(route('billing.exchange-rates'));

    app(Tenancy::class)->set($tenant);
    $created = ExchangeRate::query()->where('quote_currency', 'EUR')->firstOrFail();
    expect($created->base_currency)->toBe('USD')
        ->and($created->rate_numerator)->toBe(91)
        ->and($created->rate_denominator)->toBe(100);
});

it('rejects duplicate effective times and different-tenant rates stay isolated', function (): void {
    $tenant = Tenant::create(['name' => 'Northline', 'slug' => 'northline', 'base_currency' => 'USD', 'collection_currency' => 'USD']);
    $otherTenant = Tenant::create(['name' => 'Southline', 'slug' => 'southline', 'base_currency' => 'USD', 'collection_currency' => 'USD']);
    $user = User::create([
        'tenant_id' => $tenant->id,
        'name' => 'Workspace owner',
        'email' => 'rates-isolation@example.test',
        'password' => Hash::make('password'),
        'role' => 'tenant_owner',
    ]);
    app(CapabilitySeeder::class)->run();
    app(Tenancy::class)->set($otherTenant);
    ExchangeRate::create([
        'base_currency' => 'USD',
        'quote_currency' => 'LBP',
        'rate_numerator' => 90000,
        'rate_denominator' => 1,
        'effective_from' => '2026-01-01',
        'source' => 'other tenant',
    ]);
    app(Tenancy::class)->set($tenant);
    $user->assignRole('tenant_owner');
    $user->forceFill(['last_authenticated_at' => now()])->save();
    ExchangeRate::create([
        'base_currency' => 'USD',
        'quote_currency' => 'LBP',
        'rate_numerator' => 91000,
        'rate_denominator' => 1,
        'effective_from' => '2026-01-01',
        'source' => 'tenant opening rate',
    ]);

    $this->actingAs($user)->get(route('billing.exchange-rates'))->assertInertia(fn ($page) => $page->where('rates.total', 1));

    app(Tenancy::class)->set($tenant);
    $this->actingAs($user)
        ->post(route('billing.exchange-rates.store'), [
            'base_currency' => 'USD',
            'quote_currency' => 'LBP',
            'rate_numerator' => 92000,
            'rate_denominator' => 1,
            'effective_from' => '2026-01-01 00:00:00',
            'source' => 'duplicate attempt',
        ])
        ->assertSessionHasErrors('effective_from');
});

it('does not expose exchange-rate administration without settings capability', function (): void {
    $tenant = Tenant::create(['name' => 'Northline', 'slug' => 'northline', 'base_currency' => 'USD', 'collection_currency' => 'USD']);
    $user = User::create([
        'tenant_id' => $tenant->id,
        'name' => 'Cashier',
        'email' => 'rates-cashier@example.test',
        'password' => Hash::make('password'),
        'role' => 'cashier',
    ]);
    app(CapabilitySeeder::class)->run();
    app(Tenancy::class)->set($tenant);
    $user->assignRole('cashier');

    $this->actingAs($user)->get(route('billing.exchange-rates'))->assertForbidden();
});
