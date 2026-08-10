<?php

use App\Actions\TransferInventoryUnit;
use App\Models\InventoryItem;
use App\Models\InventoryMovement;
use App\Models\InventoryUnit;
use App\Models\Tenant;
use App\Models\User;
use App\Models\Warehouse;
use App\Support\Tenancy;
use Database\Seeders\CapabilitySeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;

uses(RefreshDatabase::class);

it('recovers a returned serialized unit into an active warehouse with an append-only movement', function (): void {
    $tenant = Tenant::create(['name' => 'Northline', 'slug' => 'northline', 'base_currency' => 'USD', 'collection_currency' => 'USD']);
    app(Tenancy::class)->set($tenant);
    $operator = User::create(['tenant_id' => $tenant->id, 'name' => 'Inventory manager', 'email' => 'inventory-transfer@example.test', 'password' => Hash::make('password'), 'role' => 'operations_manager']);
    app(CapabilitySeeder::class)->run();
    $operator->assignRole('operations_manager');
    $source = Warehouse::create(['name' => 'Recovery bay', 'code' => 'RECOVERY', 'is_active' => true]);
    $destination = Warehouse::create(['name' => 'Main warehouse', 'code' => 'MAIN', 'is_active' => true]);
    $item = InventoryItem::create(['sku' => 'ONT-RECOVER', 'name' => 'Fiber ONT', 'category' => 'onu', 'is_serialized' => true]);
    $unit = InventoryUnit::create(['inventory_item_id' => $item->id, 'warehouse_id' => $source->id, 'serial_number' => 'ONT-RECOVER-001', 'status' => 'returned', 'returned_at' => now()->subHour()]);

    app(TransferInventoryUnit::class)->handle($unit, $destination, $operator);

    app(Tenancy::class)->set($tenant);
    expect($unit->refresh()->warehouse_id)->toBe($destination->id)
        ->and($unit->status)->toBe('available')
        ->and(InventoryMovement::query()->where('inventory_unit_id', $unit->id)->where('movement_type', 'transfer')->value('from_warehouse_id'))->toBe($source->id)
        ->and(InventoryMovement::query()->where('inventory_unit_id', $unit->id)->where('movement_type', 'transfer')->value('to_warehouse_id'))->toBe($destination->id);
});
