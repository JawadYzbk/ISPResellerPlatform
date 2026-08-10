<?php

use App\Enums\ServiceStatus;
use App\Models\CurrentSession;
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

it('includes the active customer service session and excludes stopped sessions', function (): void {
    $tenant = Tenant::create(['name' => 'Northline', 'slug' => 'northline', 'base_currency' => 'USD', 'collection_currency' => 'USD']);
    $user = User::create(['tenant_id' => $tenant->id, 'name' => 'Customer care', 'email' => 'customer-session-page@example.test', 'password' => Hash::make('password'), 'role' => 'tenant_owner']);
    app(CapabilitySeeder::class)->run();
    app(Tenancy::class)->set($tenant);
    $user->assignRole('tenant_owner');
    $service = Service::factory()->create(['status' => ServiceStatus::Active]);
    $item = InventoryItem::create(['sku' => 'CPE-ONU', 'name' => 'Fiber ONU', 'category' => 'onu', 'is_serialized' => true]);
    $warehouse = Warehouse::create(['name' => 'Main warehouse', 'code' => 'MAIN']);
    InventoryUnit::create(['inventory_item_id' => $item->id, 'warehouse_id' => $warehouse->id, 'serial_number' => 'ONU-001', 'status' => 'assigned', 'service_id' => $service->id, 'assigned_at' => now()->subDay()]);
    CurrentSession::create(['service_id' => $service->id, 'username' => $service->username, 'acct_session_id' => 'customer-session-001', 'nasname' => 'router-01', 'framed_ip' => '10.0.0.20', 'last_seen_at' => now()]);
    CurrentSession::create(['service_id' => $service->id, 'username' => $service->username, 'acct_session_id' => 'customer-session-stopped', 'nasname' => 'router-01', 'stopped_at' => now()->subMinute(), 'last_seen_at' => now()->subMinutes(2)]);

    $this->actingAs($user)
        ->get(route('customers.show', $service->customer->public_id))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('Customers/Show')
            ->where('customer.services.0.session.acct_session_id', 'customer-session-001')
            ->where('customer.services.0.session.framed_ip', '10.0.0.20')
            ->where('customer.services.0.equipment.0.serial_number', 'ONU-001')
            ->where('canDisconnectSessions', true)
        );
});
