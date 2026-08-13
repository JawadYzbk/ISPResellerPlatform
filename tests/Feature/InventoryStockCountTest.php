<?php

use App\Models\InventoryItem;
use App\Models\InventoryStockCount;
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

it('posts an approved physical count as an audited variance', function (): void {
    $tenant = Tenant::create(['name' => 'Countline', 'slug' => 'countline', 'base_currency' => 'USD', 'collection_currency' => 'LBP']);
    app(Tenancy::class)->set($tenant);
    $collector = User::create(['tenant_id' => $tenant->id, 'name' => 'Collector', 'email' => 'stock-counter@example.test', 'password' => Hash::make('password'), 'role' => 'collector']);
    $manager = User::create(['tenant_id' => $tenant->id, 'name' => 'Manager', 'email' => 'stock-count-manager@example.test', 'password' => Hash::make('password'), 'role' => 'operations_manager']);
    app(CapabilitySeeder::class)->run();
    $collector->assignRole('collector');
    $manager->assignRole('operations_manager');
    $manager->forceFill(['last_authenticated_at' => now()])->save();
    $warehouse = Warehouse::create(['name' => 'Collector stock', 'code' => 'COL-01', 'type' => 'collector', 'assigned_user_id' => $collector->id]);
    $item = InventoryItem::create(['sku' => 'RJ45', 'name' => 'RJ45', 'category' => 'connector', 'is_serialized' => false]);
    $balance = StockBalance::create(['inventory_item_id' => $item->id, 'warehouse_id' => $warehouse->id, 'quantity' => '20.000']);

    $this->actingAs($collector)->post(route('field.stock-counts.store'), [
        'warehouse_id' => $warehouse->id,
        'lines' => [['inventory_item_id' => $item->id, 'counted_quantity' => '18.000']],
        'note' => 'End of route count',
    ])->assertRedirect(route('field.index'))->assertSessionHas('success', 'Stock count submitted.');

    app(Tenancy::class)->set($tenant);
    $count = InventoryStockCount::query()->with('lines')->firstOrFail();
    expect($count->lines[0]->expected_quantity)->toBe('20.000')
        ->and($count->lines[0]->variance_quantity)->toBe('-2.000')
        ->and($balance->refresh()->quantity)->toBe('20.000');

    $this->actingAs($manager)->patch(route('operations.inventory.stock-counts.review', $count), [
        'decision' => 'posted',
        'review_note' => 'Two damaged connectors confirmed',
    ])->assertRedirect(route('operations.inventory'))->assertSessionHas('success', 'Stock count posted.');

    app(Tenancy::class)->set($tenant);
    $movement = StockMovement::query()->where('movement_type', 'count_adjustment')->firstOrFail();
    expect($balance->refresh()->quantity)->toBe('18.000')
        ->and($movement->quantity)->toBe('-2.000')
        ->and($movement->metadata['stock_count_id'])->toBe($count->public_id);
});

it('refuses to post a stale count after stock has moved', function (): void {
    $tenant = Tenant::create(['name' => 'Staleline', 'slug' => 'staleline', 'base_currency' => 'USD', 'collection_currency' => 'USD']);
    app(Tenancy::class)->set($tenant);
    $collector = User::create(['tenant_id' => $tenant->id, 'name' => 'Collector', 'email' => 'stale-counter@example.test', 'password' => Hash::make('password'), 'role' => 'collector']);
    $manager = User::create(['tenant_id' => $tenant->id, 'name' => 'Manager', 'email' => 'stale-manager@example.test', 'password' => Hash::make('password'), 'role' => 'operations_manager']);
    app(CapabilitySeeder::class)->run();
    $collector->assignRole('collector');
    $manager->assignRole('operations_manager');
    $manager->forceFill(['last_authenticated_at' => now()])->save();
    $warehouse = Warehouse::create(['name' => 'Collector stock', 'code' => 'COL-02', 'type' => 'collector', 'assigned_user_id' => $collector->id]);
    $item = InventoryItem::create(['sku' => 'CLIP', 'name' => 'Clip', 'category' => 'fitting', 'is_serialized' => false]);
    $balance = StockBalance::create(['inventory_item_id' => $item->id, 'warehouse_id' => $warehouse->id, 'quantity' => '10.000']);

    $this->actingAs($collector)->post(route('field.stock-counts.store'), ['warehouse_id' => $warehouse->id, 'lines' => [['inventory_item_id' => $item->id, 'counted_quantity' => '9.000']]]);
    app(Tenancy::class)->set($tenant);
    $count = InventoryStockCount::query()->firstOrFail();
    $balance->forceFill(['quantity' => '8.000'])->save();

    $this->actingAs($manager)->patch(route('operations.inventory.stock-counts.review', $count), ['decision' => 'posted'])
        ->assertSessionHasErrors('decision');

    expect($count->refresh()->status)->toBe('pending')
        ->and($balance->refresh()->quantity)->toBe('8.000')
        ->and(StockMovement::query()->where('movement_type', 'count_adjustment')->count())->toBe(0);
});
