<?php

use App\Models\Customer;
use App\Models\Tenant;
use App\Models\User;
use App\Support\Tenancy;
use Database\Seeders\CapabilitySeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;

uses(RefreshDatabase::class);

it('imports and reverses balances through the billing adjustments API', function (): void {
    $tenant = Tenant::create(['name' => 'Northline', 'slug' => 'northline', 'base_currency' => 'USD', 'collection_currency' => 'USD']);
    app(Tenancy::class)->set($tenant);
    $customer = Customer::factory()->create(['code' => 'CUS-001']);
    $user = User::create(['tenant_id' => $tenant->id, 'name' => 'Billing', 'email' => 'balance-import@example.test', 'password' => Hash::make('password'), 'role' => 'billing_manager']);
    app(CapabilitySeeder::class)->run();
    $user->assignRole('billing_manager');
    $token = $user->createToken('balance-importer', ['api', 'staff:operator'])->plainTextToken;
    $csv = "customer_code,amount_minor,currency\nCUS-001,3500,USD";

    $response = $this->withToken($token)->postJson('/api/v1/imports/balances', ['filename' => 'balances.csv', 'csv' => $csv]);
    $response->assertCreated()->assertJsonPath('type', 'balances')->assertJsonPath('successful_rows', 1);
    app(Tenancy::class)->set($tenant);
    expect($customer->refresh()->balance_amount)->toBe(3500);

    $this->withToken($token)->postJson('/api/v1/imports/balances/'.$response->json('id').'/rollback')
        ->assertOk()
        ->assertJsonPath('status', 'rolled_back')
        ->assertJsonPath('reversed_balances', 1);
    app(Tenancy::class)->set($tenant);
    expect($customer->refresh()->balance_amount)->toBe(0);
});

it('rejects balance imports without billing adjustment capability', function (): void {
    $tenant = Tenant::create(['name' => 'Northline', 'slug' => 'northline', 'base_currency' => 'USD', 'collection_currency' => 'USD']);
    app(Tenancy::class)->set($tenant);
    $user = User::create(['tenant_id' => $tenant->id, 'name' => 'Collector', 'email' => 'balance-import-collector@example.test', 'password' => Hash::make('password'), 'role' => 'collector']);
    app(CapabilitySeeder::class)->run();
    $user->assignRole('collector');
    $token = $user->createToken('balance-importer', ['api', 'staff:operator'])->plainTextToken;

    $this->withToken($token)->postJson('/api/v1/imports/balances', [
        'csv' => "customer_code,amount_minor,currency\nCUS-001,3500,USD",
    ])->assertForbidden();
});
