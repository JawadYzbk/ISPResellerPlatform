<?php

use App\Models\Customer;
use App\Models\Tenant;
use App\Models\User;
use App\Support\Tenancy;
use Database\Seeders\CapabilitySeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;

uses(RefreshDatabase::class);

it('creates and partially updates customers through the operator API', function (): void {
    $tenant = Tenant::create(['name' => 'Northline', 'slug' => 'northline', 'base_currency' => 'USD', 'collection_currency' => 'USD']);
    app(Tenancy::class)->set($tenant);
    $user = User::create(['tenant_id' => $tenant->id, 'name' => 'Operations', 'email' => 'customer-write@example.test', 'password' => Hash::make('password'), 'role' => 'operations_manager']);
    app(CapabilitySeeder::class)->run();
    $user->assignRole('operations_manager');
    $token = $user->createToken('customer-write-api', ['api', 'staff:operator'])->plainTextToken;

    $created = $this->withToken($token)->postJson('/api/v1/customers', [
        'first_name' => 'Ada',
        'last_name' => 'Lovelace',
        'phone' => '+96170123456',
        'email' => 'ada@example.test',
    ])->assertCreated()
        ->assertJsonPath('first_name', 'Ada')
        ->assertJsonPath('status', 'active')
        ->json('id');

    $this->withToken($token)->patchJson('/api/v1/customers/'.$created, [
        'phone' => '+96171111222',
        'address' => 'Beirut',
    ])->assertOk()
        ->assertJsonPath('id', $created)
        ->assertJsonPath('phone', '+96171111222')
        ->assertJsonPath('address', 'Beirut');

    expect(Customer::withoutGlobalScopes()->where('tenant_id', $tenant->id)->where('public_id', $created)->firstOrFail()->phone_normalized)->not->toBeNull();
});

it('does not allow a staff reader to create customers', function (): void {
    $tenant = Tenant::create(['name' => 'Northline', 'slug' => 'northline', 'base_currency' => 'USD', 'collection_currency' => 'USD']);
    app(Tenancy::class)->set($tenant);
    $user = User::create(['tenant_id' => $tenant->id, 'name' => 'Support', 'email' => 'customer-reader@example.test', 'password' => Hash::make('password'), 'role' => 'support_agent']);
    app(CapabilitySeeder::class)->run();
    $user->assignRole('support_agent');
    $token = $user->createToken('customer-reader-api', ['api', 'staff:operator'])->plainTextToken;

    $this->withToken($token)->postJson('/api/v1/customers', [
        'first_name' => 'Grace',
        'phone' => '+96170123456',
    ])->assertForbidden();
});
