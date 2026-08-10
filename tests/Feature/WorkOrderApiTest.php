<?php

use App\Enums\ServiceStatus;
use App\Enums\WorkOrderStatus;
use App\Models\Customer;
use App\Models\Service;
use App\Models\Tenant;
use App\Models\User;
use App\Models\WorkOrder;
use App\Support\Tenancy;
use Database\Seeders\CapabilitySeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;

uses(RefreshDatabase::class);

it('lists and reads operator work orders through the API', function (): void {
    $tenant = Tenant::create(['name' => 'Northline', 'slug' => 'northline', 'base_currency' => 'USD', 'collection_currency' => 'USD']);
    app(Tenancy::class)->set($tenant);
    $user = User::create(['tenant_id' => $tenant->id, 'name' => 'Operations', 'email' => 'work-order-api@example.test', 'password' => Hash::make('password'), 'role' => 'operations_manager']);
    app(CapabilitySeeder::class)->run();
    $user->assignRole('operations_manager');
    $customer = Customer::factory()->create();
    $service = Service::factory()->create(['customer_id' => $customer->id, 'status' => ServiceStatus::Pending]);
    $order = WorkOrder::create([
        'number' => 'WO-API-001',
        'type' => 'installation',
        'customer_id' => $customer->id,
        'service_id' => $service->id,
        'status' => WorkOrderStatus::Pending,
        'checklist' => ['signal_test' => false],
        'metadata' => ['source' => 'api-test'],
    ]);
    $token = $user->createToken('work-order-api', ['api', 'staff:operator'])->plainTextToken;

    $this->withToken($token)->getJson('/api/v1/work-orders?filter[status]=pending&filter[search]=WO-API')
        ->assertOk()
        ->assertJsonPath('data.0.id', $order->public_id)
        ->assertJsonPath('data.0.customer.id', $customer->public_id)
        ->assertJsonPath('data.0.checklist.signal_test', false);

    $this->withToken($token)->getJson('/api/v1/work-orders/'.$order->public_id)
        ->assertOk()
        ->assertJsonPath('id', $order->public_id)
        ->assertJsonPath('service.id', $service->public_id)
        ->assertJsonMissingPath('metadata');
});

it('does not expose work orders to staff without work-order access', function (): void {
    $tenant = Tenant::create(['name' => 'Northline', 'slug' => 'northline', 'base_currency' => 'USD', 'collection_currency' => 'USD']);
    app(Tenancy::class)->set($tenant);
    $user = User::create(['tenant_id' => $tenant->id, 'name' => 'Billing', 'email' => 'work-order-reader@example.test', 'password' => Hash::make('password'), 'role' => 'billing_manager']);
    app(CapabilitySeeder::class)->run();
    $user->assignRole('billing_manager');
    $token = $user->createToken('work-order-reader-api', ['api', 'staff:operator'])->plainTextToken;

    $this->withToken($token)->getJson('/api/v1/work-orders')->assertForbidden();
});
