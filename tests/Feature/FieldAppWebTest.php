<?php

use App\Models\Customer;
use App\Models\Tenant;
use App\Models\User;
use App\Support\Tenancy;
use Database\Seeders\CapabilitySeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Http;

uses(RefreshDatabase::class);

it('serves the collector field surface from the authenticated web session', function (): void {
    $tenant = Tenant::create(['name' => 'Northline', 'slug' => 'northline', 'base_currency' => 'USD', 'collection_currency' => 'LBP']);
    app(Tenancy::class)->set($tenant);
    $user = User::create(['tenant_id' => $tenant->id, 'name' => 'Collector', 'email' => 'field@example.test', 'password' => Hash::make('password'), 'role' => 'collector']);
    app(CapabilitySeeder::class)->run();
    $user->assignRole('collector');
    $customer = Customer::factory()->create(['first_name' => 'Rami']);

    Http::fake(['https://api.frankfurter.dev/v2/currencies' => Http::response([
        ['iso_code' => 'LBP', 'name' => 'Lebanese Pound'],
        ['iso_code' => 'USD', 'name' => 'United States Dollar'],
    ])]);
    Cache::forget('currency-catalog:'.sha1((string) config('services.frankfurter.endpoint')));

    $this->actingAs($user)
        ->get(route('field.index'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('Field/Index')
            ->where('snapshot.data.customers.0.id', $customer->public_id)
            ->where('currencies.0.code', 'USD')
            ->where('currencies.1.code', 'EUR')
            ->where('currencies.2.code', 'LBP'));
});

it('refreshes the collector field snapshot through the web session', function (): void {
    $tenant = Tenant::create(['name' => 'Southline', 'slug' => 'southline', 'base_currency' => 'USD', 'collection_currency' => 'USD']);
    app(Tenancy::class)->set($tenant);
    $user = User::create(['tenant_id' => $tenant->id, 'name' => 'Collector', 'email' => 'field-sync@example.test', 'password' => Hash::make('password'), 'role' => 'collector']);
    app(CapabilitySeeder::class)->run();
    $user->assignRole('collector');

    $this->actingAs($user)
        ->getJson(route('field.sync'))
        ->assertOk()
        ->assertJsonStructure(['sync_token', 'generated_at', 'data' => ['customers', 'services', 'plans', 'exchange_rates', 'message_templates'], 'tombstones']);
});

it('rejects an empty field payment queue before dispatching it', function (): void {
    $tenant = Tenant::create(['name' => 'Eastline', 'slug' => 'eastline', 'base_currency' => 'USD', 'collection_currency' => 'USD']);
    app(Tenancy::class)->set($tenant);
    $user = User::create(['tenant_id' => $tenant->id, 'name' => 'Collector', 'email' => 'field-push@example.test', 'password' => Hash::make('password'), 'role' => 'collector']);
    app(CapabilitySeeder::class)->run();
    $user->assignRole('collector');

    $this->actingAs($user)
        ->postJson(route('field.push'), ['items' => []])
        ->assertStatus(422);
});
