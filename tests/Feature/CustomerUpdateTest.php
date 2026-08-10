<?php

use App\Models\Customer;
use App\Models\Tenant;
use App\Models\User;
use App\Models\Zone;
use App\Support\Tenancy;
use Database\Seeders\CapabilitySeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;

uses(RefreshDatabase::class);

it('renders and persists a tenant-scoped customer edit', function (): void {
    $tenant = Tenant::create(['name' => 'Northline', 'slug' => 'northline', 'base_currency' => 'USD', 'collection_currency' => 'USD']);
    $user = User::create(['tenant_id' => $tenant->id, 'name' => 'Owner', 'email' => 'owner@example.test', 'password' => Hash::make('password'), 'role' => 'tenant_owner']);
    app(CapabilitySeeder::class)->run();
    app(Tenancy::class)->set($tenant);
    $user->assignRole('tenant_owner');
    $zone = Zone::create(['tenant_id' => $tenant->id, 'name' => 'North', 'code' => 'N']);
    $customer = Customer::factory()->create(['first_name' => 'Rami', 'phone' => '+961 70 111 111']);

    $this->actingAs($user)
        ->get(route('customers.edit', $customer->public_id))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('Customers/Edit')
            ->where('customer.public_id', $customer->public_id)
            ->where('customer.zone_id', null)
        );

    app(Tenancy::class)->set($tenant);
    $this->actingAs($user)
        ->put(route('customers.update', $customer->public_id), [
            'first_name' => 'Rami Updated',
            'last_name' => 'Haddad',
            'phone' => '+961 70 222 222',
            'email' => 'rami@example.test',
            'zone_id' => $zone->id,
            'address' => 'Main street',
            'latitude' => '33.89',
            'longitude' => '35.50',
        ])
        ->assertRedirect(route('customers.show', $customer->public_id));

    app(Tenancy::class)->set($tenant);
    $updated = $customer->refresh();

    expect($updated->first_name)->toBe('Rami Updated')
        ->and($updated->phone_normalized)->toBe('96170222222')
        ->and($updated->zone_id)->toBe($zone->id)
        ->and($updated->address)->toBe('Main street');
});

it('does not expose another tenant customer to the edit route', function (): void {
    $tenant = Tenant::create(['name' => 'Northline', 'slug' => 'northline', 'base_currency' => 'USD', 'collection_currency' => 'USD']);
    $otherTenant = Tenant::create(['name' => 'Southline', 'slug' => 'southline', 'base_currency' => 'USD', 'collection_currency' => 'USD']);
    $user = User::create(['tenant_id' => $tenant->id, 'name' => 'Owner', 'email' => 'owner@example.test', 'password' => Hash::make('password'), 'role' => 'tenant_owner']);
    app(CapabilitySeeder::class)->run();
    app(Tenancy::class)->set($tenant);
    $user->assignRole('tenant_owner');
    app(Tenancy::class)->set($otherTenant);
    $customer = Customer::factory()->create();
    app(Tenancy::class)->set($tenant);

    $this->actingAs($user)->get(route('customers.edit', $customer->public_id))->assertNotFound();
});
