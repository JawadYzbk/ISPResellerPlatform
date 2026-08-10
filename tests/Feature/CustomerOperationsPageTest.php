<?php

use App\Enums\ServiceStatus;
use App\Models\Customer;
use App\Models\Service;
use App\Models\Tenant;
use App\Models\User;
use App\Models\Zone;
use App\Support\Tenancy;
use Database\Seeders\CapabilitySeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;

uses(RefreshDatabase::class);

it('filters the customer directory by zone and service expiry', function (): void {
    $tenant = Tenant::create(['name' => 'Northline', 'slug' => 'northline', 'base_currency' => 'USD', 'collection_currency' => 'USD']);
    $user = User::create(['tenant_id' => $tenant->id, 'name' => 'Customer care', 'email' => 'customers-page@example.test', 'password' => Hash::make('password'), 'role' => 'tenant_owner']);
    app(CapabilitySeeder::class)->run();
    app(Tenancy::class)->set($tenant);
    $user->assignRole('tenant_owner');
    $zone = Zone::factory()->create(['name' => 'North zone']);
    $matching = Customer::factory()->create(['zone_id' => $zone->id, 'first_name' => 'Matching']);
    $outsideZone = Customer::factory()->create(['first_name' => 'Outside zone']);
    Service::factory()->create(['customer_id' => $matching->id, 'status' => ServiceStatus::Active, 'expires_at' => '2026-08-20']);
    Service::factory()->create(['customer_id' => $outsideZone->id, 'status' => ServiceStatus::Active, 'expires_at' => '2026-08-20']);

    $this->actingAs($user)
        ->get(route('customers.index', ['zone_id' => $zone->id, 'expires_to' => '2026-08-31']))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('Customers/Index')
            ->where('customers.total', 1)
            ->where('customers.data.0.first_name', 'Matching')
            ->where('filters.zone_id', (string) $zone->id)
            ->where('filters.expires_to', '2026-08-31')
            ->where('zones', fn ($zones) => collect($zones)->pluck('name')->contains('North zone'))
        );
});

it('rejects invalid customer expiry filters and keeps the directory tenant-scoped', function (): void {
    $tenant = Tenant::create(['name' => 'Northline', 'slug' => 'northline', 'base_currency' => 'USD', 'collection_currency' => 'USD']);
    $otherTenant = Tenant::create(['name' => 'Southline', 'slug' => 'southline', 'base_currency' => 'USD', 'collection_currency' => 'USD']);
    $user = User::create(['tenant_id' => $tenant->id, 'name' => 'Customer care', 'email' => 'customers-isolation@example.test', 'password' => Hash::make('password'), 'role' => 'tenant_owner']);
    app(CapabilitySeeder::class)->run();
    app(Tenancy::class)->set($otherTenant);
    Customer::factory()->create(['first_name' => 'Southline customer']);
    app(Tenancy::class)->set($tenant);
    $user->assignRole('tenant_owner');

    $this->actingAs($user)->get(route('customers.index', ['expires_from' => '2026-09-01', 'expires_to' => '2026-08-01']))->assertSessionHasErrors('expires_to');
    $this->actingAs($user)->get(route('customers.index'))->assertInertia(fn ($page) => $page->where('customers.total', 0));
});
