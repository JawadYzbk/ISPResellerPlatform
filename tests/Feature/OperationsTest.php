<?php

use App\Actions\AssignInventoryUnit;
use App\Actions\CompleteWorkOrder;
use App\Enums\ServiceStatus;
use App\Enums\WorkOrderStatus;
use App\Models\InventoryItem;
use App\Models\InventoryUnit;
use App\Models\Service;
use App\Models\Tenant;
use App\Models\Warehouse;
use App\Models\WorkOrder;
use App\Support\Tenancy;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('keeps a serialised inventory unit in one place', function (): void {
    $tenant = Tenant::create(['name' => 'Northline', 'slug' => 'northline', 'base_currency' => 'USD', 'collection_currency' => 'USD']);
    app(Tenancy::class)->set($tenant);
    $warehouse = Warehouse::create(['name' => 'Main', 'code' => 'MAIN']);
    $item = InventoryItem::create(['sku' => 'ONT-001', 'name' => 'Optical terminal', 'category' => 'cpe', 'is_serialized' => true]);
    $unit = InventoryUnit::create(['inventory_item_id' => $item->id, 'warehouse_id' => $warehouse->id, 'serial_number' => 'SN-001']);
    $service = Service::factory()->create();

    app(AssignInventoryUnit::class)->handle($unit, $service);

    expect($unit->refresh()->service_id)->toBe($service->id)
        ->and(fn (): InventoryUnit => app(AssignInventoryUnit::class)->handle($unit, Service::factory()->create()))->toThrow(DomainException::class);
});

it('completes installation work once and activates the service', function (): void {
    $tenant = Tenant::create(['name' => 'Southline', 'slug' => 'southline', 'base_currency' => 'USD', 'collection_currency' => 'USD']);
    app(Tenancy::class)->set($tenant);
    $service = Service::factory()->create(['status' => ServiceStatus::Pending]);
    $order = WorkOrder::create(['number' => 'WO-00001', 'type' => 'installation', 'service_id' => $service->id, 'status' => WorkOrderStatus::InProgress]);

    app(CompleteWorkOrder::class)->handle($order);
    app(CompleteWorkOrder::class)->handle($order->refresh());

    expect($order->refresh()->status)->toBe(WorkOrderStatus::Completed)
        ->and($service->refresh()->status)->toBe(ServiceStatus::Active)
        ->and($order->events()->count())->toBe(1);
});
