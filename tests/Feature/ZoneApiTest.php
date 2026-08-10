<?php

use App\Models\Tenant;
use App\Models\User;
use App\Models\Zone;
use App\Support\Tenancy;
use Database\Seeders\CapabilitySeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;

uses(RefreshDatabase::class);

it('lists and reads tenant zones through the customer API scope', function (): void {
    $tenant = Tenant::create(['name' => 'Northline', 'slug' => 'northline', 'base_currency' => 'USD', 'collection_currency' => 'USD']);
    app(Tenancy::class)->set($tenant);
    $user = User::create(['tenant_id' => $tenant->id, 'name' => 'Operations', 'email' => 'zone-api@example.test', 'password' => Hash::make('password'), 'role' => 'operations_manager']);
    app(CapabilitySeeder::class)->run();
    $user->assignRole('operations_manager');
    $zone = Zone::factory()->create(['name' => 'North zone', 'code' => 'NORTH']);
    $token = $user->createToken('zone-api', ['api', 'staff:operator'])->plainTextToken;

    $this->withToken($token)->getJson('/api/v1/zones?filter[search]=NORTH')
        ->assertOk()
        ->assertJsonPath('data.0.id', $zone->id)
        ->assertJsonPath('data.0.code', 'NORTH');

    $this->withToken($token)->getJson('/api/v1/zones/'.$zone->id)
        ->assertOk()
        ->assertJsonPath('id', $zone->id)
        ->assertJsonPath('name', 'North zone');
});

it('does not expose zones to staff without customer access', function (): void {
    $tenant = Tenant::create(['name' => 'Northline', 'slug' => 'northline', 'base_currency' => 'USD', 'collection_currency' => 'USD']);
    app(Tenancy::class)->set($tenant);
    $user = User::create(['tenant_id' => $tenant->id, 'name' => 'Customer', 'email' => 'zone-reader@example.test', 'password' => Hash::make('password'), 'role' => 'customer']);
    app(CapabilitySeeder::class)->run();
    $user->assignRole('customer');
    $token = $user->createToken('zone-reader-api', ['api', 'customer'])->plainTextToken;

    $this->withToken($token)->getJson('/api/v1/zones')->assertForbidden();
});
