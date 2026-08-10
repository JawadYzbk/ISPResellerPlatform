<?php

use App\Models\InventoryItem;
use App\Models\InventoryUnit;
use App\Models\Tenant;
use App\Models\User;
use App\Models\Warehouse;
use App\Support\Tenancy;
use Database\Seeders\CapabilitySeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;

uses(RefreshDatabase::class);

it('lists only inventory in the technician warehouse', function (): void {
    $tenant = Tenant::create(['name' => 'Northline', 'slug' => 'northline', 'base_currency' => 'USD', 'collection_currency' => 'USD']);
    app(Tenancy::class)->set($tenant);
    $user = User::create(['tenant_id' => $tenant->id, 'name' => 'Tech', 'email' => 'inventory-tech@example.test', 'password' => Hash::make('password'), 'role' => 'technician']);
    app(CapabilitySeeder::class)->run();
    $user->assignRole('technician');
    $van = Warehouse::create(['name' => 'Tech Van', 'code' => 'VAN-01', 'type' => 'van', 'assigned_user_id' => $user->id]);
    $main = Warehouse::create(['name' => 'Main', 'code' => 'MAIN', 'type' => 'warehouse']);
    $item = InventoryItem::create(['sku' => 'ONT-001', 'name' => 'Optical terminal', 'category' => 'cpe', 'is_serialized' => true]);
    InventoryUnit::create(['inventory_item_id' => $item->id, 'warehouse_id' => $van->id, 'serial_number' => 'SN-VAN-001', 'status' => 'available']);
    InventoryUnit::create(['inventory_item_id' => $item->id, 'warehouse_id' => $main->id, 'serial_number' => 'SN-MAIN-001', 'status' => 'available']);
    $token = $user->createToken('technician', ['api', 'staff:technician'])->plainTextToken;

    $this->withToken($token)->getJson('/api/v1/technician/inventory')
        ->assertOk()
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.code', 'VAN-01')
        ->assertJsonPath('data.0.units.0.serial_number', 'SN-VAN-001');
});
