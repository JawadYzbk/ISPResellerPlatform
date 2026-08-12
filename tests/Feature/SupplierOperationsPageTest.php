<?php

use App\Models\Currency;
use App\Models\JournalEntry;
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
    expect(SupplierPayment::query()->sum('amount'))->toBe(200)
        ->and(SupplierBill::query()->sole()->journal_entry_id)->not->toBeNull()
        ->and(SupplierPayment::query()->sole()->journal_entry_id)->not->toBeNull()
        ->and($bill->refresh()->status)->toBe('open');

    $this->actingAs($user)
        ->post(route('operations.supplier-bills.payments.store', $bill), ['amount' => 300, 'paid_at' => '2026-08-20', 'method' => 'bank_transfer', 'reference' => 'TRX-002'])
        ->assertRedirect(route('operations.suppliers'));
    app(Tenancy::class)->set($tenant);
    expect($bill->refresh()->status)->toBe('paid')
        ->and(SupplierContract::query()->count())->toBe(1)
        ->and(SupplierPayment::query()->count())->toBe(2)
        ->and(JournalEntry::query()->where('source_type', SupplierBill::class)->count())->toBe(1)
        ->and(JournalEntry::query()->where('source_type', SupplierPayment::class)->count())->toBe(2);
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

it('updates and deactivates a supplier without removing its history', function (): void {
    $tenant = Tenant::create(['name' => 'Northline', 'slug' => 'northline', 'base_currency' => 'USD', 'collection_currency' => 'USD']);
    $user = supplierOperator($tenant, 'supplier-update@example.test');
    app(Tenancy::class)->set($tenant);
    $supplier = Supplier::create([
        'name' => 'Transit ISP',
        'code' => 'TRANSIT',
        'contact_email' => 'old@transit.test',
        'is_active' => true,
    ]);

    $this->actingAs($user)
        ->patch(route('operations.suppliers.update', $supplier), [
            'name' => 'Transit Networks',
            'code' => 'TRANSIT-NETWORKS',
            'contact_email' => 'billing@transit.test',
            'is_active' => false,
        ])
        ->assertRedirect(route('operations.suppliers'));

    expect($supplier->refresh()->only(['name', 'code', 'contact_email', 'is_active']))
        ->toMatchArray([
            'name' => 'Transit Networks',
            'code' => 'TRANSIT-NETWORKS',
            'contact_email' => 'billing@transit.test',
            'is_active' => false,
        ]);
});

it('updates a supplier contract without changing its tenant or linked credentials', function (): void {
    $tenant = Tenant::create(['name' => 'Northline', 'slug' => 'northline', 'base_currency' => 'USD', 'collection_currency' => 'USD']);
    $user = supplierOperator($tenant, 'supplier-contract-update@example.test');
    app(Tenancy::class)->set($tenant);
    $supplier = Supplier::create(['name' => 'Transit ISP', 'code' => 'TRANSIT']);
    $contract = SupplierContract::create([
        'supplier_id' => $supplier->id,
        'service_type' => 'upstream_credential',
        'wholesale_currency' => 'USD',
        'effective_from' => '2026-08-01',
        'status' => 'active',
    ]);

    $this->actingAs($user)
        ->patch(route('operations.suppliers.contracts.update', $contract), [
            'service_type' => 'managed_upstream',
            'wholesale_currency' => 'USD',
            'effective_from' => '2026-08-15',
            'effective_to' => '2027-08-14',
            'status' => 'suspended',
        ])
        ->assertRedirect(route('operations.suppliers'));

    $updated = $contract->refresh();

    expect($updated->supplier_id)->toBe($supplier->id)
        ->and($updated->service_type)->toBe('managed_upstream')
        ->and($updated->effective_from->toDateString())->toBe('2026-08-15')
        ->and($updated->effective_to?->toDateString())->toBe('2027-08-14')
        ->and($updated->status)->toBe('suspended');
});
