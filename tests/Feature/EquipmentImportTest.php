<?php

use App\Actions\ImportEquipmentCsv;
use App\Actions\RollbackImport;
use App\Models\InventoryItem;
use App\Models\InventoryMovement;
use App\Models\InventoryUnit;
use App\Models\Tenant;
use App\Models\Warehouse;
use App\Support\Tenancy;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('previews equipment rows with SKU, warehouse and assignment validation', function (): void {
    $tenant = Tenant::factory()->create(['name' => 'Northline', 'slug' => 'northline']);
    app(Tenancy::class)->set($tenant);
    InventoryItem::create(['sku' => 'ONT-001', 'name' => 'Optical terminal', 'category' => 'cpe', 'is_serialized' => true]);
    Warehouse::create(['name' => 'Main', 'code' => 'MAIN', 'type' => 'warehouse']);

    $batch = app(ImportEquipmentCsv::class)->handle($tenant, implode("\n", [
        'sku,warehouse_code,serial_number,status',
        'ONT-001,MAIN,SN-001,available',
        'MISSING,MAIN,SN-002,assigned',
    ]), 'equipment.csv', dryRun: true);

    expect($batch->successful_rows)->toBe(1)
        ->and($batch->failed_rows)->toBe(1)
        ->and($batch->report[1]['errors'])->toContain('sku does not exist')
        ->and($batch->report[1]['errors'])->toContain('service_username is required for assigned equipment')
        ->and(InventoryUnit::count())->toBe(0);
});

it('imports equipment and protects units with inventory movement history', function (): void {
    $tenant = Tenant::factory()->create(['name' => 'Northline', 'slug' => 'northline']);
    app(Tenancy::class)->set($tenant);
    InventoryItem::create(['sku' => 'ONT-001', 'name' => 'Optical terminal', 'category' => 'cpe', 'is_serialized' => true]);
    Warehouse::create(['name' => 'Main', 'code' => 'MAIN', 'type' => 'warehouse']);

    $batch = app(ImportEquipmentCsv::class)->handle($tenant, implode("\n", [
        'sku,warehouse_code,serial_number,status',
        'ONT-001,MAIN,SN-001,available',
    ]), 'equipment.csv');

    expect(app(RollbackImport::class)->handle($batch))->toBe(1)
        ->and($batch->refresh()->status)->toBe('rolled_back')
        ->and(InventoryUnit::count())->toBe(0);

    $secondBatch = app(ImportEquipmentCsv::class)->handle($tenant, implode("\n", [
        'sku,warehouse_code,serial_number,status',
        'ONT-001,MAIN,SN-002,available',
    ]), 'equipment.csv');
    $unit = InventoryUnit::query()->where('serial_number', 'SN-002')->firstOrFail();
    InventoryMovement::create(['inventory_unit_id' => $unit->id, 'from_warehouse_id' => $unit->warehouse_id, 'to_warehouse_id' => null, 'movement_type' => 'receive']);

    expect(fn (): int => app(RollbackImport::class)->handle($secondBatch))
        ->toThrow(DomainException::class, 'inventory movement');
});
