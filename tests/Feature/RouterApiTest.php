<?php

use App\Models\Router;
use App\Models\Tenant;
use App\Models\User;
use App\Support\Tenancy;
use Database\Seeders\CapabilitySeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;

uses(RefreshDatabase::class);

it('lists and reads routers without exposing credentials', function (): void {
    $tenant = Tenant::create(['name' => 'Northline', 'slug' => 'northline', 'base_currency' => 'USD', 'collection_currency' => 'USD']);
    app(Tenancy::class)->set($tenant);
    $user = User::create(['tenant_id' => $tenant->id, 'name' => 'Network', 'email' => 'router-api@example.test', 'password' => Hash::make('password'), 'role' => 'network_administrator']);
    app(CapabilitySeeder::class)->run();
    $user->assignRole('network_administrator');
    $router = Router::create([
        'name' => 'Core router',
        'host' => '10.0.0.1',
        'api_port' => 8728,
        'username' => 'router-api',
        'password_encrypted' => 'router-secret',
        'coa_port' => 1700,
        'tls_verify' => false,
        'status' => 'online',
    ]);
    $token = $user->createToken('router-api', ['api', 'staff:operator'])->plainTextToken;

    $this->withToken($token)->getJson('/api/v1/routers?filter[status]=online')
        ->assertOk()
        ->assertJsonPath('data.0.id', $router->public_id)
        ->assertJsonPath('data.0.host', '10.0.0.1')
        ->assertJsonMissingPath('data.0.password_encrypted');

    $this->withToken($token)->getJson('/api/v1/routers/'.$router->public_id)
        ->assertOk()
        ->assertJsonPath('id', $router->public_id)
        ->assertJsonMissingPath('password_encrypted');
});

it('does not expose routers to staff without network access', function (): void {
    $tenant = Tenant::create(['name' => 'Northline', 'slug' => 'northline', 'base_currency' => 'USD', 'collection_currency' => 'USD']);
    app(Tenancy::class)->set($tenant);
    $user = User::create(['tenant_id' => $tenant->id, 'name' => 'Billing', 'email' => 'router-reader@example.test', 'password' => Hash::make('password'), 'role' => 'billing_manager']);
    app(CapabilitySeeder::class)->run();
    $user->assignRole('billing_manager');
    $token = $user->createToken('router-reader-api', ['api', 'staff:operator'])->plainTextToken;

    $this->withToken($token)->getJson('/api/v1/routers')->assertForbidden();
});
