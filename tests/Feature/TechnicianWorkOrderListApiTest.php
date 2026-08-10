<?php

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

it('lists and shows only work orders assigned to the technician', function (): void {
    $tenant = Tenant::create(['name' => 'Northline', 'slug' => 'northline', 'base_currency' => 'USD', 'collection_currency' => 'USD']);
    app(Tenancy::class)->set($tenant);
    $user = User::create(['tenant_id' => $tenant->id, 'name' => 'Tech', 'email' => 'tech-list@example.test', 'password' => Hash::make('password'), 'role' => 'technician']);
    app(CapabilitySeeder::class)->run();
    $user->assignRole('technician');
    $customer = Customer::factory()->create();
    $service = Service::factory()->create(['customer_id' => $customer->id, 'status' => 'pending']);
    $assigned = WorkOrder::create(['number' => 'WO-TECH-001', 'type' => 'installation', 'customer_id' => $service->customer_id, 'service_id' => $service->id, 'assigned_to' => $user->id, 'status' => WorkOrderStatus::Assigned, 'scheduled_at' => now()->startOfDay()]);
    WorkOrder::create(['number' => 'WO-OTHER-001', 'type' => 'repair', 'status' => WorkOrderStatus::Pending]);
    $token = $user->createToken('technician', ['api', 'staff:technician'])->plainTextToken;

    $this->withToken($token)->getJson('/api/v1/technician/work-orders?status=assigned')
        ->assertOk()
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.id', $assigned->public_id)
        ->assertJsonPath('data.0.service.username', $service->username);

    $this->withToken($token)->getJson('/api/v1/technician/work-orders/'.$assigned->public_id)
        ->assertOk()
        ->assertJsonPath('id', $assigned->public_id)
        ->assertJsonPath('customer.id', $customer->public_id);

    $this->withToken($token)->getJson('/api/v1/technician/work-orders/WO-OTHER-001')->assertNotFound();
});
