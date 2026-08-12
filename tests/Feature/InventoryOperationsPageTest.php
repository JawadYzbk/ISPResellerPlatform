<?php

use App\Models\InventoryItem;
use App\Models\InventoryMovement;
use App\Models\InventoryUnit;
use App\Models\Service;
use App\Models\StockMovement;
use App\Models\Tenant;
use App\Models\User;
use App\Models\Warehouse;
use App\Support\Tenancy;
use Database\Seeders\CapabilitySeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;

uses(RefreshDatabase::class);

it('renders tenant-safe serialized inventory with service assignment context', function (): void {
    $tenant = Tenant::create(['name' => 'Northline', 'slug' => 'northline', 'base_currency' => 'USD', 'collection_currency' => 'USD']);
    $user = User::create(['tenant_id' => $tenant->id, 'name' => 'Technician', 'email' => 'technician@example.test', 'password' => Hash::make('password'), 'role' => 'technician']);
    app(CapabilitySeeder::class)->run();
    app(Tenancy::class)->set($tenant);
    $user->assignRole('technician');
    $item = InventoryItem::create(['sku' => 'CPE-ONU', 'name' => 'Fiber ONU', 'category' => 'onu', 'is_serialized' => true]);
    $warehouse = Warehouse::create(['name' => 'Main warehouse', 'code' => 'MAIN']);
    $service = Service::factory()->create();
    $unit = InventoryUnit::create(['inventory_item_id' => $item->id, 'warehouse_id' => $warehouse->id, 'serial_number' => 'ONU-001', 'status' => 'assigned', 'service_id' => $service->id, 'assigned_at' => now()]);
    InventoryMovement::create(['inventory_unit_id' => $unit->id, 'from_warehouse_id' => $warehouse->id, 'service_id' => $service->id, 'movement_type' => 'assign', 'actor_id' => $user->id]);
    $bulkItem = InventoryItem::create(['sku' => 'CABLE-001', 'name' => 'Outdoor cable', 'category' => 'cable', 'is_serialized' => false]);
    StockMovement::create(['inventory_item_id' => $bulkItem->id, 'warehouse_id' => $warehouse->id, 'actor_id' => $user->id, 'movement_type' => 'receive', 'quantity' => '10.500', 'occurred_at' => now()->subMinute(), 'note' => 'Opening stock']);

    $this->actingAs($user)
        ->get(route('operations.inventory', ['status' => 'assigned']))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('Operations/Inventory')
            ->where('units.data.0.serial_number', 'ONU-001')
            ->where('units.data.0.item.sku', 'CPE-ONU')
            ->where('units.data.0.service.username', $service->username)
            ->where('movements.0.serial_number', 'ONU-001')
            ->where('movements.1.quantity', '10.500')
            ->where('filters.status', 'assigned')
        );
});

it('does not expose inventory units from another tenant', function (): void {
    $tenant = Tenant::create(['name' => 'Northline', 'slug' => 'northline', 'base_currency' => 'USD', 'collection_currency' => 'USD']);
    $otherTenant = Tenant::create(['name' => 'Southline', 'slug' => 'southline', 'base_currency' => 'USD', 'collection_currency' => 'USD']);
    $user = User::create(['tenant_id' => $tenant->id, 'name' => 'Technician', 'email' => 'technician@example.test', 'password' => Hash::make('password'), 'role' => 'technician']);
    app(CapabilitySeeder::class)->run();
    app(Tenancy::class)->set($otherTenant);
    $item = InventoryItem::create(['sku' => 'CPE-ONU', 'name' => 'Fiber ONU', 'category' => 'onu', 'is_serialized' => true]);
    $warehouse = Warehouse::create(['name' => 'South warehouse', 'code' => 'SOUTH']);
    $unit = InventoryUnit::create(['inventory_item_id' => $item->id, 'warehouse_id' => $warehouse->id, 'serial_number' => 'ONU-SOUTH', 'status' => 'available']);
    app(Tenancy::class)->set($tenant);
    $user->assignRole('technician');

    $this->actingAs($user)->get(route('operations.inventory'))->assertOk()->assertInertia(fn ($page) => $page->where('units.total', 0));
    expect($unit->serial_number)->toBe('ONU-SOUTH');
});

it('assigns an available unit through the tenant-safe web action', function (): void {
    $tenant = Tenant::create(['name' => 'Northline', 'slug' => 'northline', 'base_currency' => 'USD', 'collection_currency' => 'USD']);
    $user = User::create(['tenant_id' => $tenant->id, 'name' => 'Technician', 'email' => 'technician-assign@example.test', 'password' => Hash::make('password'), 'role' => 'technician']);
    app(CapabilitySeeder::class)->run();
    app(Tenancy::class)->set($tenant);
    $user->assignRole('technician');
    $item = InventoryItem::create(['sku' => 'CPE-ONU', 'name' => 'Fiber ONU', 'category' => 'onu', 'is_serialized' => true]);
    $warehouse = Warehouse::create(['name' => 'Main warehouse', 'code' => 'MAIN']);
    $service = Service::factory()->create(['username' => 'assignable-service']);
    $unit = InventoryUnit::create(['inventory_item_id' => $item->id, 'warehouse_id' => $warehouse->id, 'serial_number' => 'ONU-002', 'status' => 'available']);
    $user->forceFill(['last_authenticated_at' => now()])->save();

    $this->actingAs($user)
        ->get(route('operations.inventory'))
        ->assertInertia(fn ($page) => $page
            ->where('canAssign', true)
            ->where('assignableServices.0.public_id', $service->public_id)
        );

    app(Tenancy::class)->set($tenant);
    $this->actingAs($user)
        ->post(route('operations.inventory.assign', $unit->id), ['service_public_id' => $service->public_id])
        ->assertRedirect(route('operations.inventory'));

    expect($unit->refresh()->service_id)->toBe($service->id)
        ->and($unit->status)->toBe('assigned');
});

it('lets an inventory manager create stock masters and receive serialized equipment', function (): void {
    $tenant = Tenant::create(['name' => 'Northline', 'slug' => 'northline', 'base_currency' => 'USD', 'collection_currency' => 'USD']);
    $user = User::create([
        'tenant_id' => $tenant->id,
        'name' => 'Inventory manager',
        'email' => 'inventory-manager@example.test',
        'password' => Hash::make('password'),
        'role' => 'tenant_owner',
        'last_authenticated_at' => now(),
    ]);
    app(CapabilitySeeder::class)->run();
    app(Tenancy::class)->set($tenant);
    $user->assignRole('tenant_owner');
    $user->forceFill(['last_authenticated_at' => now()])->save();

    $this->actingAs($user)
        ->post(route('operations.inventory.items.store'), [
            'sku' => 'CPE-ONU',
            'name' => 'Fiber ONU',
            'category' => 'onu',
            'is_serialized' => true,
            'reorder_level' => 2,
        ])
        ->assertRedirect(route('operations.inventory'))
        ->assertSessionHas('success', 'Inventory item CPE-ONU created.');

    $this->actingAs($user)
        ->post(route('operations.inventory.warehouses.store'), [
            'name' => 'Main warehouse',
            'code' => 'main',
            'type' => 'warehouse',
        ])
        ->assertRedirect(route('operations.inventory'))
        ->assertSessionHas('success', 'Warehouse MAIN created.');

    app(Tenancy::class)->set($tenant);
    $item = InventoryItem::query()->where('sku', 'CPE-ONU')->firstOrFail();
    $warehouse = Warehouse::query()->where('code', 'MAIN')->firstOrFail();

    $this->actingAs($user)
        ->post(route('operations.inventory.serialized-receive'), [
            'inventory_item_id' => $item->id,
            'warehouse_id' => $warehouse->id,
            'serial_number' => 'ONU-0001',
        ])
        ->assertRedirect(route('operations.inventory'))
        ->assertSessionHas('success', 'Serialized unit ONU-0001 received.');

    $this->actingAs($user)
        ->patch(route('operations.inventory.items.update', $item), [
            'sku' => 'CPE-ONU-V2',
            'name' => 'Fiber ONU v2',
            'category' => 'onu',
            'is_serialized' => true,
            'reorder_level' => 3,
            'is_active' => true,
        ])
        ->assertRedirect(route('operations.inventory'))
        ->assertSessionHas('success', 'Inventory item CPE-ONU-V2 updated.');

    $this->actingAs($user)
        ->patch(route('operations.inventory.warehouses.update', $warehouse), [
            'name' => 'Central warehouse',
            'code' => 'CENTRAL',
            'type' => 'warehouse',
            'is_active' => true,
        ])
        ->assertRedirect(route('operations.inventory'))
        ->assertSessionHas('success', 'Warehouse CENTRAL updated.');

    app(Tenancy::class)->set($tenant);
    expect($item->refresh()->sku)->toBe('CPE-ONU-V2')
        ->and($warehouse->refresh()->code)->toBe('CENTRAL')
        ->and(InventoryUnit::query()->where('serial_number', 'ONU-0001')->value('warehouse_id'))->toBe($warehouse->id)
        ->and(InventoryMovement::query()->where('movement_type', 'receive')->where('inventory_unit_id', InventoryUnit::query()->where('serial_number', 'ONU-0001')->value('id'))->exists())->toBeTrue();
});
