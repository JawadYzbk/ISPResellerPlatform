<?php

use App\Models\Currency;
use App\Models\Supplier;
use App\Models\SupplierBill;
use App\Models\SupplierContract;
use App\Models\SupplierPayment;
use App\Models\Tenant;
use App\Models\User;
use App\Support\Tenancy;
use Database\Seeders\CapabilitySeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Spatie\Permission\Models\Role;

uses(RefreshDatabase::class);

function supplierOperator(Tenant $tenant, string $email = 'supplier-operator@example.test'): User
{
    $user = User::create(['tenant_id' => $tenant->id, 'name' => 'Supplier operator', 'email' => $email, 'password' => Hash::make('password'), 'role' => 'tenant_owner']);
    app(Tenancy::class)->set($tenant);
    app(CapabilitySeeder::class)->run();
    $user->assignRole('tenant_owner');
    $user->forceFill(['last_authenticated_at' => now()])->save();
    Currency::firstOrCreate(['code' => 'USD'], ['name' => 'United States Dollar', 'decimal_digits' => 2, 'is_active' => true]);

    return $user;
}

it('manages supplier contracts, bills and partial payments', function (): void {
    $tenant = Tenant::create(['name' => 'Northline', 'slug' => 'northline', 'base_currency' => 'USD', 'collection_currency' => 'USD']);
    $user = supplierOperator($tenant);

    $this->actingAs($user)
        ->post(route('operations.suppliers.store'), ['name' => 'Transit ISP', 'code' => 'transit', 'contact_email' => 'billing@transit.test'])
        ->assertRedirect(route('operations.suppliers'));
    app(Tenancy::class)->set($tenant);
    $supplier = Supplier::query()->firstOrFail();

    $this->actingAs($user)
        ->post(route('operations.suppliers.contracts.store', $supplier), ['service_type' => 'upstream_credential', 'wholesale_currency' => 'USD', 'effective_from' => '2026-08-01', 'status' => 'active'])
        ->assertRedirect(route('operations.suppliers'));
    $this->actingAs($user)
        ->post(route('operations.suppliers.bills.store', $supplier), ['reference' => 'BILL-001', 'period_start' => '2026-08-01', 'period_end' => '2026-08-31', 'amount' => 500, 'currency' => 'USD'])
        ->assertRedirect(route('operations.suppliers'));
    app(Tenancy::class)->set($tenant);
    $bill = SupplierBill::query()->firstOrFail();

    $this->actingAs($user)
        ->post(route('operations.supplier-bills.payments.store', $bill), ['amount' => 200, 'paid_at' => '2026-08-10', 'method' => 'bank_transfer', 'reference' => 'TRX-001'])
        ->assertRedirect(route('operations.suppliers'));
    app(Tenancy::class)->set($tenant);
    expect(SupplierPayment::query()->sum('amount'))->toBe(200)->and($bill->refresh()->status)->toBe('open');

    $this->actingAs($user)
        ->post(route('operations.supplier-bills.payments.store', $bill), ['amount' => 300, 'paid_at' => '2026-08-20', 'method' => 'bank_transfer', 'reference' => 'TRX-002'])
        ->assertRedirect(route('operations.suppliers'));
    app(Tenancy::class)->set($tenant);
    expect($bill->refresh()->status)->toBe('paid')
        ->and(SupplierContract::query()->count())->toBe(1);
});

it('isolates supplier workspaces and forbids supplier writes without management capability', function (): void {
    $tenant = Tenant::create(['name' => 'Northline', 'slug' => 'northline', 'base_currency' => 'USD', 'collection_currency' => 'USD']);
    $other = Tenant::create(['name' => 'Southline', 'slug' => 'southline', 'base_currency' => 'USD', 'collection_currency' => 'USD']);
    $user = supplierOperator($tenant, 'supplier-isolation@example.test');
    app(CapabilitySeeder::class)->run();
    app(Tenancy::class)->set($other);
    Supplier::create(['name' => 'South Transit', 'code' => 'SOUTH']);
    app(Tenancy::class)->set($tenant);

    $this->actingAs($user)->get(route('operations.suppliers'))->assertOk()->assertInertia(fn ($page) => $page->where('suppliers', []));

    app(Tenancy::class)->set($tenant);
    $supportRole = Role::findOrCreate('support_agent', 'web');
    $user->syncRoles([$supportRole]);
    $this->actingAs($user)
        ->post(route('operations.suppliers.store'), ['name' => 'Blocked', 'code' => 'BLOCKED'])
        ->assertForbidden();
});
