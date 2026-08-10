<?php

use App\Enums\WorkOrderStatus;
use App\Models\Tenant;
use App\Models\User;
use App\Models\WorkOrder;
use App\Models\WorkOrderEvent;
use App\Support\Tenancy;
use Database\Seeders\CapabilitySeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;

uses(RefreshDatabase::class);

it('records assigned technician readings through the API and history', function (): void {
    $tenant = Tenant::create(['name' => 'Northline', 'slug' => 'northline', 'base_currency' => 'USD', 'collection_currency' => 'USD']);
    app(Tenancy::class)->set($tenant);
    $user = User::create(['tenant_id' => $tenant->id, 'name' => 'Technician', 'email' => 'readings-tech@example.test', 'password' => Hash::make('password'), 'role' => 'technician']);
    app(CapabilitySeeder::class)->run();
    $user->assignRole('technician');
    $order = WorkOrder::create(['number' => 'WO-READINGS-001', 'type' => 'repair', 'assigned_to' => $user->id, 'status' => WorkOrderStatus::InProgress]);
    $token = $user->createToken('field-device', ['api', 'staff:technician'])->plainTextToken;

    $this->withToken($token)->postJson('/api/v1/technician/work-orders/'.$order->public_id.'/readings', [
        'readings' => ['signal_dbm' => '-61', 'ccq' => '92', 'ap_name' => 'POP-NORTH-01'],
    ])->assertOk()->assertJsonPath('readings.signal_dbm', '-61');

    app(Tenancy::class)->set($tenant);
    expect($order->refresh()->readings)->toBe(['signal_dbm' => '-61', 'ccq' => '92', 'ap_name' => 'POP-NORTH-01'])
        ->and(WorkOrderEvent::query()->where('work_order_id', $order->id)->where('event_type', 'readings_recorded')->count())->toBe(1);
});

it('records readings through the operator work-order page', function (): void {
    $tenant = Tenant::create(['name' => 'Southline', 'slug' => 'southline', 'base_currency' => 'USD', 'collection_currency' => 'USD']);
    app(Tenancy::class)->set($tenant);
    $user = User::create(['tenant_id' => $tenant->id, 'name' => 'Operations manager', 'email' => 'readings-operator@example.test', 'password' => Hash::make('password'), 'role' => 'operations_manager']);
    app(CapabilitySeeder::class)->run();
    $user->assignRole('operations_manager');
    $user->forceFill(['last_authenticated_at' => now()])->save();
    $order = WorkOrder::create(['number' => 'WO-READINGS-PAGE-001', 'type' => 'fiber', 'assigned_to' => $user->id, 'status' => WorkOrderStatus::Assigned]);

    $this->actingAs($user)->post(route('operations.work-orders.readings.store', $order->public_id), [
        'readings' => ['optical_rx_dbm' => '-18.5', 'ont_serial' => 'ONT-100'],
    ])->assertRedirect(route('operations.work-orders.show', $order->public_id));

    app(Tenancy::class)->set($tenant);
    expect($order->refresh()->readings)->toBe(['optical_rx_dbm' => '-18.5', 'ont_serial' => 'ONT-100']);
});
