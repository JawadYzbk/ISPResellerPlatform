<?php

use App\Models\InventoryItem;
use App\Models\InventoryUnit;
use App\Models\Service;
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

    $this->actingAs($user)
        ->get(route('operations.inventory', ['status' => 'assigned']))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('Operations/Inventory')
            ->where('units.data.0.serial_number', 'ONU-001')
            ->where('units.data.0.item.sku', 'CPE-ONU')
            ->where('units.data.0.service.username', $service->username)
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
