<?php

use App\Enums\ServiceStatus;
use App\Enums\WorkOrderStatus;
use App\Models\Customer;
use App\Models\MediaUpload;
use App\Models\Service;
use App\Models\Tenant;
use App\Models\User;
use App\Models\WorkOrder;
use App\Models\WorkOrderEvent;
use App\Support\Tenancy;
use Database\Seeders\CapabilitySeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;

uses(RefreshDatabase::class);

it('renders work orders and completes an installation through the existing action', function (): void {
    Queue::fake();
    Storage::fake('local');
    $tenant = Tenant::create(['name' => 'Northline', 'slug' => 'northline', 'base_currency' => 'USD', 'collection_currency' => 'USD']);
    $user = User::create(['tenant_id' => $tenant->id, 'name' => 'Operations manager', 'email' => 'operations@example.test', 'password' => Hash::make('password'), 'role' => 'operations_manager']);
    app(CapabilitySeeder::class)->run();
    app(Tenancy::class)->set($tenant);
    $user->assignRole('operations_manager');
    $service = Service::factory()->create(['status' => ServiceStatus::Pending]);
    $customer = Customer::query()->findOrFail($service->customer_id);
    $order = WorkOrder::create([
        'number' => 'WO-00001',
        'type' => 'installation',
        'customer_id' => $service->customer_id,
        'service_id' => $service->id,
        'assigned_to' => $user->id,
        'status' => WorkOrderStatus::Assigned,
        'scheduled_at' => now()->addHour(),
        'checklist' => ['ont_installed' => false, 'signal_verified' => true],
    ]);
    $mediaPath = 'media/'.$tenant->id.'/router-rack.jpg';
    Storage::disk('local')->put($mediaPath, 'fake-image');
    $media = MediaUpload::create([
        'work_order_id' => $order->id,
        'uploaded_by_id' => $user->id,
        'disk' => 'local',
        'path' => $mediaPath,
        'original_name' => 'router-rack.jpg',
        'mime_type' => 'image/jpeg',
        'size_bytes' => 10,
        'sha256' => str_repeat('a', 64),
        'purpose' => 'evidence',
    ]);

    $this->actingAs($user)
        ->get(route('operations.work-orders', ['status' => 'assigned']))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('Operations/WorkOrders')
            ->where('workOrders.data.0.number', 'WO-00001')
            ->where('workOrders.data.0.checklist.signal_verified', true)
            ->where('filters.status', 'assigned')
        );

    app(Tenancy::class)->set($tenant);
    $this->actingAs($user)
        ->get(route('operations.work-orders.show', $order->public_id))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('Operations/WorkOrderShow')
            ->where('workOrder.number', 'WO-00001')
            ->where('workOrder.checklist.ont_installed', false)
            ->where('workOrder.customer.public_id', $customer->public_id)
            ->where('workOrder.media.0.id', $media->public_id)
            ->where('workOrder.media.0.filename', 'router-rack.jpg')
        );

    app(Tenancy::class)->set($tenant);
    $this->actingAs($user)
        ->get(route('operations.media.download', $media->public_id))
        ->assertOk()
        ->assertHeader('Content-Type', 'image/jpeg');

    app(Tenancy::class)->set($tenant);
    $this->actingAs($user)
        ->post(route('operations.work-orders.complete', $order->public_id), ['idempotency_key' => '0198d9a4-0e80-72bb-9ef8-44a7bf6c2189'])
        ->assertRedirect(route('operations.work-orders'))
        ->assertSessionHas('success', 'Work order WO-00001 completed.');

    app(Tenancy::class)->set($tenant);
    expect($order->refresh()->status)->toBe(WorkOrderStatus::Completed)
        ->and($service->refresh()->status)->toBe(ServiceStatus::Active);
});

it('does not expose work orders from another tenant', function (): void {
    $tenant = Tenant::create(['name' => 'Northline', 'slug' => 'northline', 'base_currency' => 'USD', 'collection_currency' => 'USD']);
    $otherTenant = Tenant::create(['name' => 'Southline', 'slug' => 'southline', 'base_currency' => 'USD', 'collection_currency' => 'USD']);
    $user = User::create(['tenant_id' => $tenant->id, 'name' => 'Operations manager', 'email' => 'operations@example.test', 'password' => Hash::make('password'), 'role' => 'operations_manager']);
    app(CapabilitySeeder::class)->run();
    app(Tenancy::class)->set($otherTenant);
    $order = WorkOrder::create(['number' => 'WO-SOUTH-001', 'type' => 'repair', 'status' => WorkOrderStatus::Assigned]);
    app(Tenancy::class)->set($tenant);
    $user->assignRole('operations_manager');

    $this->actingAs($user)->get(route('operations.work-orders'))->assertOk()->assertInertia(fn ($page) => $page->where('workOrders.total', 0));
    $this->actingAs($user)->get(route('operations.work-orders.show', $order->public_id))->assertNotFound();
    $this->actingAs($user)->post(route('operations.work-orders.complete', $order->public_id))->assertNotFound();
});

it('reschedules an active work order and records the schedule change', function (): void {
    $tenant = Tenant::create(['name' => 'Northline', 'slug' => 'northline', 'base_currency' => 'USD', 'collection_currency' => 'USD', 'timezone' => 'Asia/Beirut']);
    $user = User::create(['tenant_id' => $tenant->id, 'name' => 'Operations manager', 'email' => 'operations-schedule@example.test', 'password' => Hash::make('password'), 'role' => 'operations_manager']);
    app(CapabilitySeeder::class)->run();
    app(Tenancy::class)->set($tenant);
    $user->assignRole('operations_manager');
    $user->forceFill(['last_authenticated_at' => now()])->save();
    $order = WorkOrder::create(['number' => 'WO-SCHEDULE-001', 'type' => 'repair', 'status' => WorkOrderStatus::Assigned, 'scheduled_at' => '2026-08-10 08:00:00']);

    $this->actingAs($user)
        ->post(route('operations.work-orders.schedule', $order->public_id), ['scheduled_at' => '2026-08-12T15:30'])
        ->assertRedirect(route('operations.work-orders.show', $order->public_id));

    app(Tenancy::class)->set($tenant);
    expect($order->refresh()->scheduled_at?->toIso8601String())->toBe('2026-08-12T12:30:00+00:00')
        ->and(WorkOrderEvent::query()->where('work_order_id', $order->id)->where('event_type', 'rescheduled')->count())->toBe(1);
});
