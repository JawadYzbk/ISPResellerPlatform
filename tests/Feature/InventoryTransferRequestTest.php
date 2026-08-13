<?php

use App\Models\InventoryItem;
use App\Models\InventoryTransferRequest;
use App\Models\StockBalance;
use App\Models\StockMovement;
use App\Models\Tenant;
use App\Models\User;
use App\Models\Warehouse;
use App\Support\Tenancy;
use Database\Seeders\CapabilitySeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;

uses(RefreshDatabase::class);

it('lets a collector request replenishment and a manager approve the stock movement', function (): void {
    $tenant = Tenant::create(['name' => 'Requestline', 'slug' => 'requestline', 'base_currency' => 'USD', 'collection_currency' => 'LBP']);
    app(Tenancy::class)->set($tenant);
    $collector = User::create(['tenant_id' => $tenant->id, 'name' => 'Nadia', 'email' => 'stock-request-collector@example.test', 'password' => Hash::make('password'), 'role' => 'collector']);
    $manager = User::create(['tenant_id' => $tenant->id, 'name' => 'Manager', 'email' => 'stock-request-manager@example.test', 'password' => Hash::make('password'), 'role' => 'operations_manager']);
    app(CapabilitySeeder::class)->run();
    $collector->assignRole('collector');
    $manager->assignRole('operations_manager');
    $manager->forceFill(['last_authenticated_at' => now()])->save();
    $central = Warehouse::create(['name' => 'Central', 'code' => 'MAIN', 'type' => 'warehouse']);
    $custody = Warehouse::create(['name' => 'Nadia stock', 'code' => 'COL-NAD', 'type' => 'collector', 'assigned_user_id' => $collector->id]);
    $item = InventoryItem::create(['sku' => 'RJ45', 'name' => 'RJ45 connector', 'category' => 'connector', 'is_serialized' => false]);
    StockBalance::create(['inventory_item_id' => $item->id, 'warehouse_id' => $central->id, 'quantity' => '100.000']);

    $this->actingAs($collector)->post(route('field.inventory-requests.store'), [
        'inventory_item_id' => $item->id,
        'source_warehouse_id' => $central->id,
        'destination_warehouse_id' => $custody->id,
        'type' => 'replenishment',
        'quantity' => '15.000',
        'note' => 'Route stock',
    ])->assertRedirect(route('field.index'))
        ->assertSessionHas('success', 'Replenishment request created.');

    app(Tenancy::class)->set($tenant);
    $stockRequest = InventoryTransferRequest::query()->firstOrFail();
    expect($stockRequest->status)->toBe('pending')
        ->and(StockBalance::query()->where('warehouse_id', $custody->id)->exists())->toBeFalse();

    $this->actingAs($manager)->patch(route('operations.inventory.requests.review', $stockRequest), [
        'decision' => 'approved',
        'review_note' => 'Prepared at the desk',
    ])->assertRedirect(route('operations.inventory'))
        ->assertSessionHas('success', 'Stock request approved.');

    app(Tenancy::class)->set($tenant);
    expect($stockRequest->refresh()->status)->toBe('approved')
        ->and($stockRequest->reviewed_by_id)->toBe($manager->id)
        ->and(StockBalance::query()->where('warehouse_id', $central->id)->value('quantity'))->toBe('85.000')
        ->and(StockBalance::query()->where('warehouse_id', $custody->id)->value('quantity'))->toBe('15.000')
        ->and(StockMovement::query()->whereIn('movement_type', ['transfer_out', 'transfer_in'])->count())->toBe(2);
});

it('rejects requests that do not use the requester assigned custody location', function (): void {
    $tenant = Tenant::create(['name' => 'Safeline', 'slug' => 'safeline', 'base_currency' => 'USD', 'collection_currency' => 'USD']);
    app(Tenancy::class)->set($tenant);
    $collector = User::create(['tenant_id' => $tenant->id, 'name' => 'Collector', 'email' => 'wrong-stock-location@example.test', 'password' => Hash::make('password'), 'role' => 'collector']);
    app(CapabilitySeeder::class)->run();
    $collector->assignRole('collector');
    $central = Warehouse::create(['name' => 'Central', 'code' => 'MAIN', 'type' => 'warehouse']);
    $other = Warehouse::create(['name' => 'Other stock', 'code' => 'COL-OTHER', 'type' => 'collector']);
    $item = InventoryItem::create(['sku' => 'CLIP', 'name' => 'Cable clip', 'category' => 'fitting', 'is_serialized' => false]);

    $this->actingAs($collector)->post(route('field.inventory-requests.store'), [
        'inventory_item_id' => $item->id,
        'source_warehouse_id' => $central->id,
        'destination_warehouse_id' => $other->id,
        'type' => 'replenishment',
        'quantity' => '2.000',
    ])->assertSessionHasErrors('quantity');

    expect(InventoryTransferRequest::query()->count())->toBe(0);
});
