<?php

use App\Enums\ServiceStatus;
use App\Models\Customer;
use App\Models\Plan;
use App\Models\Service;
use App\Models\Tenant;
use App\Models\User;
use App\Support\Tenancy;
use Database\Seeders\CapabilitySeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;

uses(RefreshDatabase::class);

it('creates a pending service through the operator API using public resource ids', function (): void {
    $tenant = Tenant::create(['name' => 'Northline', 'slug' => 'northline', 'base_currency' => 'USD', 'collection_currency' => 'USD']);
    app(Tenancy::class)->set($tenant);
    $user = User::create(['tenant_id' => $tenant->id, 'name' => 'Operations', 'email' => 'service-write@example.test', 'password' => Hash::make('password'), 'role' => 'operations_manager']);
    app(CapabilitySeeder::class)->run();
    $user->assignRole('operations_manager');
    $customer = Customer::factory()->create();
    $plan = Plan::factory()->create(['status' => 'active']);
    $token = $user->createToken('service-write-api', ['api', 'staff:operator'])->plainTextToken;

    $created = $this->withToken($token)->postJson('/api/v1/services', [
        'customer_id' => $customer->public_id,
        'plan_id' => $plan->public_id,
        'username' => 'ada.home',
        'password' => 'a-secure-service-password',
        'provisioning_mode' => 'manual',
    ])->assertCreated()
        ->assertJsonPath('status', 'pending')
        ->assertJsonPath('customer.id', $customer->public_id)
        ->assertJsonPath('plan.id', $plan->public_id)
        ->assertJsonMissingPath('password_encrypted')
        ->json('id');

    app(Tenancy::class)->set($tenant);
    expect(Service::query()->where('public_id', $created)->firstOrFail()->status)->toBe(ServiceStatus::Pending);
});

it('does not allow a staff reader to create services', function (): void {
    $tenant = Tenant::create(['name' => 'Northline', 'slug' => 'northline', 'base_currency' => 'USD', 'collection_currency' => 'USD']);
    app(Tenancy::class)->set($tenant);
    $user = User::create(['tenant_id' => $tenant->id, 'name' => 'Support', 'email' => 'service-reader@example.test', 'password' => Hash::make('password'), 'role' => 'support_agent']);
    app(CapabilitySeeder::class)->run();
    $user->assignRole('support_agent');
    $customer = Customer::factory()->create();
    $plan = Plan::factory()->create(['status' => 'active']);
    $token = $user->createToken('service-reader-api', ['api', 'staff:operator'])->plainTextToken;

    $this->withToken($token)->postJson('/api/v1/services', [
        'customer_id' => $customer->public_id,
        'plan_id' => $plan->public_id,
        'username' => 'support.home',
        'password' => 'a-secure-service-password',
        'provisioning_mode' => 'manual',
    ])->assertForbidden();
});
