<?php

use App\Models\CashShift;
use App\Models\Customer;
use App\Models\FieldInventorySale;
use App\Models\InventoryItem;
use App\Models\Payment;
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

it('records a paid customer invoice and consumes collector stock atomically', function (): void {
    $tenant = Tenant::create(['name' => 'Salesline', 'slug' => 'salesline', 'base_currency' => 'USD', 'collection_currency' => 'USD']);
    app(Tenancy::class)->set($tenant);
    $collector = User::create(['tenant_id' => $tenant->id, 'name' => 'Collector', 'email' => 'field-sale@example.test', 'password' => Hash::make('password'), 'role' => 'collector']);
    app(CapabilitySeeder::class)->run();
    $collector->assignRole('collector');
    $customer = Customer::factory()->create(['balance_currency' => 'USD']);
    $warehouse = Warehouse::create(['name' => 'Collector stock', 'code' => 'COL-SALE', 'type' => 'collector', 'assigned_user_id' => $collector->id]);
    $item = InventoryItem::create(['sku' => 'RJ45', 'name' => 'RJ45 connector', 'category' => 'connector', 'is_serialized' => false]);
    $balance = StockBalance::create(['inventory_item_id' => $item->id, 'warehouse_id' => $warehouse->id, 'quantity' => '20.000']);
    CashShift::create(['user_id' => $collector->id, 'status' => 'open', 'opened_at' => now(), 'opening_float' => []]);

    $this->actingAs($collector)->post(route('field.inventory-sales.store'), [
        'customer_id' => $customer->public_id,
        'warehouse_id' => $warehouse->id,
        'currency' => 'USD',
        'payment_method' => 'cash',
        'idempotency_key' => 'field-sale-test-001',
        'lines' => [['inventory_item_id' => $item->id, 'quantity' => '2.500', 'unit_amount' => 200]],
        'note' => 'Connectors sold during collection route',
    ])->assertRedirect(route('field.index'));

    app(Tenancy::class)->set($tenant);
    $sale = FieldInventorySale::query()->with(['invoice', 'payment', 'lines'])->firstOrFail();
    expect($sale->total_amount)->toBe(500)
        ->and($sale->invoice->status->value)->toBe('issued')
        ->and($sale->payment->amount)->toBe(500)
        ->and($sale->payment->invoice_id)->toBe($sale->invoice_id)
        ->and($balance->refresh()->quantity)->toBe('17.500')
        ->and(StockMovement::query()->where('movement_type', 'field_sale')->value('quantity'))->toBe('-2.500')
        ->and($customer->refresh()->balance_amount)->toBe(0);

    $this->actingAs($collector)->post(route('field.inventory-sales.store'), [
        'customer_id' => $customer->public_id,
        'warehouse_id' => $warehouse->id,
        'currency' => 'USD',
        'payment_method' => 'cash',
        'idempotency_key' => 'field-sale-test-001',
        'lines' => [['inventory_item_id' => $item->id, 'quantity' => '2.500', 'unit_amount' => 200]],
    ]);

    app(Tenancy::class)->set($tenant);
    expect(FieldInventorySale::query()->count())->toBe(1)
        ->and(Payment::query()->count())->toBe(1)
        ->and($balance->refresh()->quantity)->toBe('17.500');
});

it('rolls back the invoice and payment when collector stock is insufficient', function (): void {
    $tenant = Tenant::create(['name' => 'Safe sales', 'slug' => 'safe-sales', 'base_currency' => 'USD', 'collection_currency' => 'USD']);
    app(Tenancy::class)->set($tenant);
    $collector = User::create(['tenant_id' => $tenant->id, 'name' => 'Collector', 'email' => 'field-sale-short@example.test', 'password' => Hash::make('password'), 'role' => 'collector']);
    app(CapabilitySeeder::class)->run();
    $collector->assignRole('collector');
    $customer = Customer::factory()->create(['balance_currency' => 'USD']);
    $warehouse = Warehouse::create(['name' => 'Collector stock', 'code' => 'COL-SHORT', 'type' => 'collector', 'assigned_user_id' => $collector->id]);
    $item = InventoryItem::create(['sku' => 'CLIP', 'name' => 'Clip', 'category' => 'fitting', 'is_serialized' => false]);
    StockBalance::create(['inventory_item_id' => $item->id, 'warehouse_id' => $warehouse->id, 'quantity' => '1.000']);
    CashShift::create(['user_id' => $collector->id, 'status' => 'open', 'opened_at' => now(), 'opening_float' => []]);

    $this->actingAs($collector)->post(route('field.inventory-sales.store'), [
        'customer_id' => $customer->public_id,
        'warehouse_id' => $warehouse->id,
        'currency' => 'USD',
        'payment_method' => 'cash',
        'idempotency_key' => 'field-sale-short-001',
        'lines' => [['inventory_item_id' => $item->id, 'quantity' => '2.000', 'unit_amount' => 100]],
    ])->assertSessionHasErrors('lines');

    expect(FieldInventorySale::query()->count())->toBe(0)
        ->and(Payment::query()->count())->toBe(0);
});
