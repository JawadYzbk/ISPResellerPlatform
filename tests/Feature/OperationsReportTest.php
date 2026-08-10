<?php

use App\Actions\GetOperationsReport;
use App\Enums\NetworkState;
use App\Enums\ServiceStatus;
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
