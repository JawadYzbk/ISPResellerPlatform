<?php

use App\Actions\TransitionService;
use App\Enums\ServiceStatus;
use App\Models\InventoryItem;
use App\Models\InventoryMovement;
use App\Models\InventoryUnit;
use App\Models\Service;
use App\Models\Tenant;
use App\Models\User;
use App\Models\Warehouse;
use App\Support\Tenancy;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;

uses(RefreshDatabase::class);

it('returns serialized service equipment to warehouse custody when a service is terminated', function (): void {
    $tenant = Tenant::create(['name' => 'Northline', 'slug' => 'northline', 'base_currency' => 'USD', 'collection_currency' => 'USD']);
    app(Tenancy::class)->set($tenant);
    $operator = User::create(['tenant_id' => $tenant->id, 'name' => 'Operator', 'email' => 'inventory-return@example.test', 'password' => Hash::make('password'), 'role' => 'operations_manager']);
    $service = Service::factory()->create(['status' => ServiceStatus::Active]);
    $item = InventoryItem::create(['sku' => 'ONT-RETURN', 'name' => 'Fiber ONT', 'category' => 'onu', 'is_serialized' => true]);
    $warehouse = Warehouse::create(['name' => 'Main warehouse', 'code' => 'MAIN']);
    $unit = InventoryUnit::create(['inventory_item_id' => $item->id, 'warehouse_id' => $warehouse->id, 'serial_number' => 'ONT-RETURN-001', 'status' => 'assigned', 'service_id' => $service->id, 'assigned_at' => now()]);

    app(TransitionService::class)->handle($service, ServiceStatus::Terminated, $operator);

    app(Tenancy::class)->set($tenant);
    expect($unit->refresh()->status)->toBe('returned')
        ->and($unit->service_id)->toBeNull()
        ->and($unit->returned_at)->not->toBeNull()
        ->and(InventoryMovement::query()->where('inventory_unit_id', $unit->id)->where('movement_type', 'return')->value('to_warehouse_id'))->toBe($warehouse->id);
});
