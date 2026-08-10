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

it('imports and rolls back equipment through the scoped API', function (): void {
    $tenant = Tenant::create(['name' => 'Northline', 'slug' => 'northline', 'base_currency' => 'USD', 'collection_currency' => 'USD']);
    app(Tenancy::class)->set($tenant);
    InventoryItem::create(['sku' => 'ONT-001', 'name' => 'Optical terminal', 'category' => 'cpe', 'is_serialized' => true]);
    Warehouse::create(['name' => 'Main', 'code' => 'MAIN', 'type' => 'warehouse']);
    $user = User::create(['tenant_id' => $tenant->id, 'name' => 'Operations', 'email' => 'equipment-import@example.test', 'password' => Hash::make('password'), 'role' => 'operations_manager']);
    app(CapabilitySeeder::class)->run();
    $user->assignRole('operations_manager');
    $token = $user->createToken('equipment-importer', ['api', 'staff:operator'])->plainTextToken;
    $csv = "sku,warehouse_code,serial_number,status\nONT-001,MAIN,SN-001,available";

    $response = $this->withToken($token)->postJson('/api/v1/imports/equipment', ['filename' => 'equipment.csv', 'csv' => $csv]);
    $response->assertCreated()->assertJsonPath('type', 'equipment')->assertJsonPath('successful_rows', 1);
    app(Tenancy::class)->set($tenant);
    expect(InventoryUnit::count())->toBe(1);

    $this->withToken($token)->postJson('/api/v1/imports/equipment/'.$response->json('id').'/rollback')
        ->assertOk()
        ->assertJsonPath('status', 'rolled_back')
        ->assertJsonPath('deleted_equipment', 1);
    app(Tenancy::class)->set($tenant);
    expect(InventoryUnit::count())->toBe(0);
});

it('rejects equipment imports without inventory receive capability', function (): void {
    $tenant = Tenant::create(['name' => 'Northline', 'slug' => 'northline', 'base_currency' => 'USD', 'collection_currency' => 'USD']);
    app(Tenancy::class)->set($tenant);
    $user = User::create(['tenant_id' => $tenant->id, 'name' => 'Collector', 'email' => 'equipment-import-collector@example.test', 'password' => Hash::make('password'), 'role' => 'collector']);
    app(CapabilitySeeder::class)->run();
    $user->assignRole('collector');
    $token = $user->createToken('equipment-importer', ['api', 'staff:operator'])->plainTextToken;

    $this->withToken($token)->postJson('/api/v1/imports/equipment', [
        'csv' => "sku,warehouse_code,serial_number\nONT-001,MAIN,SN-001",
    ])->assertForbidden();
});
