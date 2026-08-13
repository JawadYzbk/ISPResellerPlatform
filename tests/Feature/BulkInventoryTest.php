<?php

use App\Enums\WorkOrderStatus;
use App\Models\InventoryItem;
use App\Models\StockBalance;
use App\Models\StockMovement;
use App\Models\Tenant;
use App\Models\User;
use App\Models\Warehouse;
use App\Models\WorkOrder;
use App\Models\WorkOrderEvent;
use App\Models\WorkOrderMaterial;
use App\Support\Tenancy;
use Database\Seeders\CapabilitySeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;

uses(RefreshDatabase::class);

it('lets an assigned technician consume bulk stock from their van through the API', function (): void {
    $tenant = Tenant::create(['name' => 'Northline', 'slug' => 'northline', 'base_currency' => 'USD', 'collection_currency' => 'USD']);
    app(Tenancy::class)->set($tenant);
    $technician = User::create(['tenant_id' => $tenant->id, 'name' => 'Field technician', 'email' => 'bulk-material-tech@example.test', 'password' => Hash::make('password'), 'role' => 'technician']);
    app(CapabilitySeeder::class)->run();
    $technician->assignRole('technician');
    $warehouse = Warehouse::create(['name' => 'Tech van', 'code' => 'VAN-01', 'type' => 'van', 'assigned_user_id' => $technician->id]);
    $item = InventoryItem::create(['sku' => 'CABLE-UTP', 'name' => 'Outdoor UTP cable', 'category' => 'cable', 'is_serialized' => false]);
    $balance = StockBalance::create(['inventory_item_id' => $item->id, 'warehouse_id' => $warehouse->id, 'quantity' => '12.500']);
    $order = WorkOrder::create(['number' => 'WO-BULK-001', 'type' => 'installation', 'assigned_to' => $technician->id, 'status' => WorkOrderStatus::InProgress]);
    $token = $technician->createToken('field-device', ['api', 'staff:technician'])->plainTextToken;

    $this->withToken($token)->postJson('/api/v1/technician/work-orders/'.$order->public_id.'/materials', [
        'inventory_item_id' => $item->id,
        'warehouse_id' => $warehouse->id,
        'quantity' => '2.500',
        'note' => 'Drop cable used at the customer site',
    ])->assertCreated()
        ->assertJsonPath('quantity', '2.500');

    app(Tenancy::class)->set($tenant);
    expect($balance->refresh()->quantity)->toBe('10.000')
        ->and(WorkOrderMaterial::query()->where('work_order_id', $order->id)->value('quantity'))->toBe('2.500')
        ->and(StockMovement::query()->where('work_order_id', $order->id)->value('quantity'))->toBe('-2.500')
        ->and(WorkOrderEvent::query()->where('work_order_id', $order->id)->where('event_type', 'material_consumed')->count())->toBe(1);
});

it('rejects bulk consumption that would make a balance negative', function (): void {
    $tenant = Tenant::create(['name' => 'Southline', 'slug' => 'southline', 'base_currency' => 'USD', 'collection_currency' => 'USD']);
    app(Tenancy::class)->set($tenant);
    $technician = User::create(['tenant_id' => $tenant->id, 'name' => 'Field technician', 'email' => 'bulk-material-short@example.test', 'password' => Hash::make('password'), 'role' => 'technician']);
    app(CapabilitySeeder::class)->run();
    $technician->assignRole('technician');
    $warehouse = Warehouse::create(['name' => 'Tech van', 'code' => 'VAN-02', 'type' => 'van', 'assigned_user_id' => $technician->id]);
    $item = InventoryItem::create(['sku' => 'RJ45-BOOT', 'name' => 'RJ45 boot', 'category' => 'connector', 'is_serialized' => false]);
    $balance = StockBalance::create(['inventory_item_id' => $item->id, 'warehouse_id' => $warehouse->id, 'quantity' => '1.000']);
    $order = WorkOrder::create(['number' => 'WO-BULK-002', 'type' => 'repair', 'assigned_to' => $technician->id, 'status' => WorkOrderStatus::Assigned]);
    $token = $technician->createToken('field-device', ['api', 'staff:technician'])->plainTextToken;

    $this->withToken($token)->postJson('/api/v1/technician/work-orders/'.$order->public_id.'/materials', [
        'inventory_item_id' => $item->id,
        'warehouse_id' => $warehouse->id,
        'quantity' => '1.001',
    ])->assertStatus(409)->assertJsonPath('message', 'Insufficient bulk stock for this material.');

    app(Tenancy::class)->set($tenant);
    expect($balance->refresh()->quantity)->toBe('1.000')
        ->and(WorkOrderMaterial::query()->where('work_order_id', $order->id)->count())->toBe(0);
});

it('receives bulk stock and consumes it from the operator work-order page', function (): void {
    $tenant = Tenant::create(['name' => 'Eastline', 'slug' => 'eastline', 'base_currency' => 'USD', 'collection_currency' => 'USD']);
    app(Tenancy::class)->set($tenant);
    $operator = User::create(['tenant_id' => $tenant->id, 'name' => 'Operations manager', 'email' => 'bulk-material-operator@example.test', 'password' => Hash::make('password'), 'role' => 'operations_manager']);
    app(CapabilitySeeder::class)->run();
    $operator->assignRole('operations_manager');
    $operator->forceFill(['last_authenticated_at' => now()])->save();
    $warehouse = Warehouse::create(['name' => 'Main warehouse', 'code' => 'MAIN']);
    $item = InventoryItem::create(['sku' => 'FIBER-CLIP', 'name' => 'Fiber clip', 'category' => 'fitting', 'is_serialized' => false]);
    $order = WorkOrder::create(['number' => 'WO-BULK-003', 'type' => 'fiber', 'assigned_to' => $operator->id, 'status' => WorkOrderStatus::Assigned]);

    $this->actingAs($operator)->post(route('operations.inventory.bulk-receive'), [
        'inventory_item_id' => $item->id,
        'warehouse_id' => $warehouse->id,
        'quantity' => '5.250',
    ])->assertRedirect(route('operations.inventory'));

    $this->actingAs($operator)->post(route('operations.work-orders.materials.store', $order->public_id), [
        'inventory_item_id' => $item->id,
        'warehouse_id' => $warehouse->id,
        'quantity' => '1.250',
    ])->assertRedirect(route('operations.work-orders.show', $order->public_id));

    app(Tenancy::class)->set($tenant);
    expect(StockBalance::query()->where('inventory_item_id', $item->id)->value('quantity'))->toBe('4.000')
        ->and(WorkOrderMaterial::query()->where('work_order_id', $order->id)->value('quantity'))->toBe('1.250');
});

it('transfers bulk stock between custody locations with paired audit movements', function (): void {
    $tenant = Tenant::create(['name' => 'Transferline', 'slug' => 'transferline', 'base_currency' => 'USD', 'collection_currency' => 'LBP']);
    app(Tenancy::class)->set($tenant);
    $operator = User::create([
        'tenant_id' => $tenant->id,
        'name' => 'Inventory operator',
        'email' => 'bulk-transfer-operator@example.test',
        'password' => Hash::make('password'),
        'role' => 'operations_manager',
        'last_authenticated_at' => now(),
    ]);
    app(CapabilitySeeder::class)->run();
    $operator->assignRole('operations_manager');
    $operator->forceFill(['last_authenticated_at' => now()])->save();
    $source = Warehouse::create(['name' => 'Main warehouse', 'code' => 'MAIN']);
    $destination = Warehouse::create(['name' => 'Collector custody', 'code' => 'COL-01', 'type' => 'collector', 'assigned_user_id' => $operator->id]);
    $item = InventoryItem::create(['sku' => 'DROP-CABLE', 'name' => 'Drop cable', 'category' => 'cable', 'is_serialized' => false]);
    $sourceBalance = StockBalance::create(['inventory_item_id' => $item->id, 'warehouse_id' => $source->id, 'quantity' => '25.000']);

    $this->actingAs($operator)->post(route('operations.inventory.bulk-transfer'), [
        'inventory_item_id' => $item->id,
        'source_warehouse_id' => $source->id,
        'destination_warehouse_id' => $destination->id,
        'quantity' => '7.500',
        'note' => 'Weekly collector replenishment',
    ])->assertRedirect(route('operations.inventory'))
        ->assertSessionHas('success', 'Bulk stock transferred.');

    app(Tenancy::class)->set($tenant);
    $movements = StockMovement::query()->where('inventory_item_id', $item->id)->orderBy('id')->get();
    expect($sourceBalance->refresh()->quantity)->toBe('17.500')
        ->and(StockBalance::query()->where('warehouse_id', $destination->id)->value('quantity'))->toBe('7.500')
        ->and($movements)->toHaveCount(2)
        ->and($movements[0]->movement_type)->toBe('transfer_out')
        ->and($movements[0]->quantity)->toBe('-7.500')
        ->and($movements[1]->movement_type)->toBe('transfer_in')
        ->and($movements[1]->quantity)->toBe('7.500')
        ->and($movements[0]->metadata['transfer_id'])->toBe($movements[1]->metadata['transfer_id']);

    $this->actingAs($operator)->post(route('operations.inventory.bulk-transfer'), [
        'inventory_item_id' => $item->id,
        'source_warehouse_id' => $source->id,
        'destination_warehouse_id' => $destination->id,
        'quantity' => '99.000',
    ])->assertSessionHasErrors('quantity');

    expect($sourceBalance->refresh()->quantity)->toBe('17.500');
});
