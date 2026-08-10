<?php

use App\Enums\ServiceStatus;
use App\Enums\WorkOrderStatus;
use App\Models\NetworkCommand;
use App\Models\Service;
use App\Models\Tenant;
use App\Models\User;
use App\Models\WorkOrder;
use App\Support\Tenancy;
use Database\Seeders\CapabilitySeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Queue;

uses(RefreshDatabase::class);

it('completes an installation once and makes retries replay the same result', function (): void {
    Queue::fake();
    $tenant = Tenant::create(['name' => 'Northline', 'slug' => 'northline', 'base_currency' => 'USD', 'collection_currency' => 'USD']);
    app(Tenancy::class)->set($tenant);
    $user = User::create(['tenant_id' => $tenant->id, 'name' => 'Tech', 'email' => 'tech@example.test', 'password' => Hash::make('password'), 'role' => 'technician']);
    app(CapabilitySeeder::class)->run();
    $user->assignRole('technician');
    $service = Service::factory()->create(['status' => ServiceStatus::Pending]);
    $workOrder = WorkOrder::create(['number' => 'WO-00001', 'type' => 'installation', 'customer_id' => $service->customer_id, 'service_id' => $service->id, 'assigned_to' => $user->id, 'status' => WorkOrderStatus::Assigned]);
    $token = $user->createToken('technician', ['api', 'staff:technician'])->plainTextToken;
    $headers = ['X-Idempotency-Key' => 'work-order-complete-001'];

    $first = $this->withToken($token)->withHeaders($headers)->postJson('/api/v1/technician/work-orders/'.$workOrder->public_id.'/complete');
    $second = $this->withToken($token)->withHeaders($headers)->postJson('/api/v1/technician/work-orders/'.$workOrder->public_id.'/complete');

    $first->assertOk()->assertJsonPath('service_id', $service->public_id);
    $second->assertOk()->assertJsonPath('id', $first->json('id'));
    expect($workOrder->refresh()->status)->toBe(WorkOrderStatus::Completed)
        ->and($service->refresh()->status)->toBe(ServiceStatus::Active)
        ->and(NetworkCommand::withoutGlobalScopes()->count())->toBe(1);
});
