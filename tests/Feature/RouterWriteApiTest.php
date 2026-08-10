<?php

use App\Models\Router;
use App\Models\Tenant;
use App\Models\User;
use App\Support\Tenancy;
use Database\Seeders\CapabilitySeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;

uses(RefreshDatabase::class);

it('creates a router through the network provisioning API without returning secrets', function (): void {
    $tenant = Tenant::create(['name' => 'Northline', 'slug' => 'northline', 'base_currency' => 'USD', 'collection_currency' => 'USD']);
    app(Tenancy::class)->set($tenant);
    $user = User::create(['tenant_id' => $tenant->id, 'name' => 'Network', 'email' => 'router-write@example.test', 'password' => Hash::make('password'), 'role' => 'network_administrator']);
    app(CapabilitySeeder::class)->run();
    $user->assignRole('network_administrator');
    $token = $user->createToken('router-write-api', ['api', 'staff:operator'])->plainTextToken;

    $created = $this->withToken($token)->postJson('/api/v1/routers', [
        'name' => 'Edge router',
        'host' => '10.0.0.2',
        'api_port' => 8728,
        'username' => 'router-api',
        'password' => 'a-secure-router-password',
        'coa_port' => 1700,
        'tls_verify' => false,
    ])->assertCreated()
        ->assertJsonPath('name', 'Edge router')
        ->assertJsonMissingPath('password_encrypted')
        ->json('id');

    expect(Router::withoutGlobalScopes()->where('public_id', $created)->value('host'))->toBe('10.0.0.2');
});

it('does not allow staff without network provisioning access to create routers', function (): void {
    $tenant = Tenant::create(['name' => 'Northline', 'slug' => 'northline', 'base_currency' => 'USD', 'collection_currency' => 'USD']);
    app(Tenancy::class)->set($tenant);
    $user = User::create(['tenant_id' => $tenant->id, 'name' => 'Billing', 'email' => 'router-writer@example.test', 'password' => Hash::make('password'), 'role' => 'billing_manager']);
    app(CapabilitySeeder::class)->run();
    $user->assignRole('billing_manager');
    $token = $user->createToken('router-writer-api', ['api', 'staff:operator'])->plainTextToken;

    $this->withToken($token)->postJson('/api/v1/routers', [
        'name' => 'Blocked router',
        'host' => '10.0.0.3',
        'api_port' => 8728,
        'username' => 'router-api',
        'password' => 'a-secure-router-password',
        'coa_port' => 1700,
    ])->assertForbidden();
});
