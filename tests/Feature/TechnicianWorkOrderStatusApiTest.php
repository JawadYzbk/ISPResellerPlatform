<?php

use App\Actions\ScheduleWorkOrder;
use App\Enums\WorkOrderStatus;
use App\Models\Tenant;
use App\Models\User;
use App\Models\WorkOrder;
use App\Models\WorkOrderEvent;
use App\Support\Tenancy;
use Carbon\CarbonImmutable;
use Database\Seeders\CapabilitySeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;

uses(RefreshDatabase::class);

it('lets the assigned technician track progress and fail a work order for rescheduling', function (): void {
    $tenant = Tenant::create(['name' => 'Northline', 'slug' => 'northline', 'base_currency' => 'USD', 'collection_currency' => 'USD']);
    app(Tenancy::class)->set($tenant);
    $user = User::create(['tenant_id' => $tenant->id, 'name' => 'Tech', 'email' => 'status-tech@example.test', 'password' => Hash::make('password'), 'role' => 'technician']);
    app(CapabilitySeeder::class)->run();
    $user->assignRole('technician');
    $order = WorkOrder::create([
        'number' => 'WO-STATUS-001',
        'type' => 'repair',
        'assigned_to' => $user->id,
        'status' => WorkOrderStatus::Assigned,
        'readings' => ['signal_dbm' => '-21'],
    ]);
    $token = $user->createToken('technician', ['api', 'staff:technician'])->plainTextToken;

    $this->withToken($token)->postJson('/api/v1/technician/work-orders/'.$order->public_id.'/status', [
        'status' => 'en_route',
        'at' => '2026-08-10T09:00:00+00:00',
    ])->assertOk()->assertJsonPath('status', 'en_route');

    $this->withToken($token)->postJson('/api/v1/technician/work-orders/'.$order->public_id.'/status', [
        'status' => 'in_progress',
        'at' => '2026-08-10T09:05:00+00:00',
    ])->assertOk()->assertJsonPath('status', 'in_progress');

    $this->withToken($token)->postJson('/api/v1/technician/work-orders/'.$order->public_id.'/fail', [
        'reason' => 'Customer was not available',
        'notes' => 'Called twice at the premises.',
        'reschedule_at' => '2026-08-11T09:00:00+00:00',
    ])->assertOk()->assertJsonPath('status', 'failed')->assertJsonPath('failure_reason', 'Customer was not available');

    app(Tenancy::class)->set($tenant);
    expect($order->refresh()->status)->toBe(WorkOrderStatus::Failed)
        ->and($order->readings)->toMatchArray(['signal_dbm' => '-21'])
        ->and(WorkOrderEvent::query()->where('work_order_id', $order->id)->pluck('event_type')->all())->toBe(['status_changed', 'status_changed', 'failed']);

    app(ScheduleWorkOrder::class)->handle($order->refresh(), $user, CarbonImmutable::parse('2026-08-12T09:00:00+00:00'));

    expect($order->refresh()->status)->toBe(WorkOrderStatus::Assigned)
        ->and($order->readings)->toMatchArray(['signal_dbm' => '-21']);
});

it('hides work-order status changes from another technician', function (): void {
    $tenant = Tenant::create(['name' => 'Southline', 'slug' => 'southline', 'base_currency' => 'USD', 'collection_currency' => 'USD']);
    app(Tenancy::class)->set($tenant);
    $assigned = User::create(['tenant_id' => $tenant->id, 'name' => 'Assigned', 'email' => 'assigned-tech@example.test', 'password' => Hash::make('password'), 'role' => 'technician']);
    $other = User::create(['tenant_id' => $tenant->id, 'name' => 'Other', 'email' => 'other-tech@example.test', 'password' => Hash::make('password'), 'role' => 'technician']);
    app(CapabilitySeeder::class)->run();
    $other->assignRole('technician');
    $order = WorkOrder::create(['number' => 'WO-STATUS-002', 'type' => 'repair', 'assigned_to' => $assigned->id, 'status' => WorkOrderStatus::Assigned]);
    $token = $other->createToken('technician', ['api', 'staff:technician'])->plainTextToken;

    $this->withToken($token)->postJson('/api/v1/technician/work-orders/'.$order->public_id.'/status', ['status' => 'in_progress'])->assertNotFound();
});
