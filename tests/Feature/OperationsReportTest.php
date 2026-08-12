<?php

use App\Actions\ExportOperationsReportCsv;
use App\Actions\GetOperationsReport;
use App\Enums\CredentialStatus;
use App\Enums\NetworkState;
use App\Enums\ServiceStatus;
use App\Models\CredentialBatch;
use App\Models\InventoryItem;
use App\Models\InventoryUnit;
use App\Models\Service;
use App\Models\StockBalance;
use App\Models\Supplier;
use App\Models\Tenant;
use App\Models\UpstreamCredential;
use App\Models\User;
use App\Models\Warehouse;
use App\Support\Tenancy;
use Carbon\CarbonImmutable;
use Database\Seeders\CapabilitySeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;

uses(RefreshDatabase::class);

it('summarizes live service and network operations by status', function (): void {
    $tenant = Tenant::factory()->create(['name' => 'Northline', 'slug' => 'northline']);
    app(Tenancy::class)->set($tenant);
    Service::factory()->create(['status' => ServiceStatus::Active, 'network_state' => NetworkState::InSync, 'expires_at' => now()->addDays(3)]);
    Service::factory()->create(['status' => ServiceStatus::Suspended, 'network_state' => NetworkState::Drifted, 'expires_at' => now()->addDays(20)]);
    Service::factory()->create(['status' => ServiceStatus::Pending, 'network_state' => NetworkState::Failed, 'expires_at' => now()->addDays(2)]);
    $item = InventoryItem::create(['sku' => 'ONT-001', 'name' => 'Optical terminal', 'category' => 'cpe', 'is_serialized' => true, 'reorder_level' => 2]);
    $warehouse = Warehouse::create(['name' => 'Main', 'code' => 'MAIN', 'type' => 'warehouse']);
    InventoryUnit::create(['inventory_item_id' => $item->id, 'warehouse_id' => $warehouse->id, 'serial_number' => 'SN-001', 'status' => 'available']);

    $report = app(GetOperationsReport::class)->handle();

    expect($report['service_counts_by_status'])->toMatchArray(['active' => 1, 'pending' => 1, 'suspended' => 1])
        ->and($report['expiring_services'])->toBe(2)
        ->and($report['network_drift'])->toBe(2)
        ->and($report['active_sessions'])->toBe(0)
        ->and($report['offline_routers'])->toBe(0)
        ->and($report['failed_commands'])->toBe(0);
    expect($report['low_stock_items'])->toMatchArray([['sku' => 'ONT-001', 'name' => 'Optical terminal', 'available_units' => 1, 'reorder_level' => 2]]);
});

it('includes bulk inventory balances in low-stock alerts', function (): void {
    $tenant = Tenant::factory()->create(['name' => 'Eastline', 'slug' => 'eastline']);
    app(Tenancy::class)->set($tenant);
    $item = InventoryItem::create(['sku' => 'CABLE-001', 'name' => 'Outdoor cable', 'category' => 'cable', 'is_serialized' => false, 'reorder_level' => 10]);
    $warehouse = Warehouse::create(['name' => 'Main', 'code' => 'MAIN-BULK', 'type' => 'warehouse']);
    StockBalance::create(['inventory_item_id' => $item->id, 'warehouse_id' => $warehouse->id, 'quantity' => '4.500']);

    $report = app(GetOperationsReport::class)->handle();

    expect($report['low_stock_items'])->toContain(['sku' => 'CABLE-001', 'name' => 'Outdoor cable', 'available_units' => '4.500', 'reorder_level' => 10]);
});

it('reconciles supplier credentials by period, state and recorded cost', function (): void {
    $tenant = Tenant::factory()->create(['name' => 'Westline', 'slug' => 'westline']);
    app(Tenancy::class)->set($tenant);
    $supplier = Supplier::create(['name' => 'Transit ISP', 'code' => 'TRANSIT']);
    $batch = CredentialBatch::create([
        'supplier_id' => $supplier->id,
        'reference' => 'TRANSIT-AUG',
        'contract_reference' => 'CONTRACT-01',
        'unit_cost_amount' => 125,
        'total_cost_amount' => 500,
        'currency' => 'USD',
        'imported_at' => '2026-08-05 12:00:00',
    ]);
    foreach ([
        ['identifier' => 'available', 'status' => CredentialStatus::Available],
        ['identifier' => 'assigned', 'status' => CredentialStatus::Assigned],
        ['identifier' => 'active', 'status' => CredentialStatus::Active, 'expires_at' => '2026-08-20'],
        ['identifier' => 'revoked', 'status' => CredentialStatus::Revoked],
        ['identifier' => 'invalid', 'status' => CredentialStatus::Invalid],
    ] as $row) {
        UpstreamCredential::create([
            'credential_batch_id' => $batch->id,
            'identifier' => $row['identifier'],
            'secret' => 'secret-'.$row['identifier'],
            'lookup_hash' => hash('sha256', $row['identifier']),
            'status' => $row['status'],
            'expires_at' => $row['expires_at'] ?? null,
        ]);
    }

    $report = app(GetOperationsReport::class)->handle(
        CarbonImmutable::parse('2026-08-01'),
        CarbonImmutable::parse('2026-08-10'),
    );

    expect($report['supplier_credentials']['totals'])->toMatchArray([
        'purchased' => 5,
        'assigned' => 2,
        'available' => 1,
        'expiring' => 1,
        'revoked_invalid' => 2,
    ]);
    expect($report['supplier_credentials']['by_supplier'][0])->toMatchArray([
        'name' => 'Transit ISP',
        'code' => 'TRANSIT',
        'purchased' => 5,
        'assigned' => 2,
        'available' => 1,
        'expiring' => 1,
        'revoked_invalid' => 2,
        'cost_by_currency' => ['USD' => 500],
    ]);

    expect(app(ExportOperationsReportCsv::class)->handle(
        CarbonImmutable::parse('2026-08-01'),
        CarbonImmutable::parse('2026-08-10'),
    ))->toContain('supplier:TRANSIT,purchased,5')->toContain('contract:TRANSIT,CONTRACT-01,USD,500');
});

it('streams the operations report for an authorised operator', function (): void {
    $tenant = Tenant::create(['name' => 'Southline', 'slug' => 'southline', 'base_currency' => 'USD', 'collection_currency' => 'USD']);
    app(Tenancy::class)->set($tenant);
    $user = User::create(['tenant_id' => $tenant->id, 'name' => 'Reports', 'email' => 'operations-report@example.test', 'password' => Hash::make('password'), 'role' => 'support_agent']);
    app(CapabilitySeeder::class)->run();
    $user->assignRole('support_agent');
    $user->givePermissionTo('reports.operations');

    $response = $this->actingAs($user)->get('/reports/operations?format=csv');

    $response->assertOk()->assertHeader('content-type', 'text/csv; charset=UTF-8')->assertStreamed();
    expect($response->streamedContent())->toContain('metric,status,total')->toContain('expiring_services');
});
