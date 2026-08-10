<?php

use App\Models\InventoryItem;
use App\Models\InventoryMovement;
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

it('returns one assigned service unit from the customer page', function (): void {
    $tenant = Tenant::create(['name' => 'Northline', 'slug' => 'northline', 'base_currency' => 'USD', 'collection_currency' => 'USD']);
    $user = User::create(['tenant_id' => $tenant->id, 'name' => 'Operations', 'email' => 'equipment-web@example.test', 'password' => Hash::make('password'), 'role' => 'tenant_owner']);
    app(CapabilitySeeder::class)->run();
    app(Tenancy::class)->set($tenant);
    $user->assignRole('tenant_owner');
    $service = Service::factory()->create();
    $item = InventoryItem::create(['sku' => 'ONT-WEB-RETURN', 'name' => 'Fiber ONT', 'category' => 'onu', 'is_serialized' => true]);
    $warehouse = Warehouse::create(['name' => 'Main warehouse', 'code' => 'MAIN']);
    $unit = InventoryUnit::create(['inventory_item_id' => $item->id, 'warehouse_id' => $warehouse->id, 'serial_number' => 'ONT-WEB-RETURN-001', 'status' => 'assigned', 'service_id' => $service->id, 'assigned_at' => now()]);
    $customerPublicId = $service->customer->public_id;
    $user->forceFill(['last_authenticated_at' => now()])->save();

    $this->actingAs($user)
        ->post(route('services.equipment.return', [$service->public_id, $unit->id]))
        ->assertRedirect(route('customers.show', $customerPublicId));

    app(Tenancy::class)->set($tenant);

    expect($unit->refresh()->status)->toBe('returned')
        ->and($unit->service_id)->toBeNull()
        ->and(InventoryMovement::query()->where('inventory_unit_id', $unit->id)->latest('id')->value('movement_type'))->toBe('return');
});
