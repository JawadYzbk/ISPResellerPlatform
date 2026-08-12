<?php

use App\Models\Supplier;
use App\Models\SupplierBill;
use App\Models\Tenant;
use App\Models\User;
use App\Support\Tenancy;
use Database\Seeders\CapabilitySeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;

uses(RefreshDatabase::class);

it('renders the tenant-safe supplier payable report with filters', function (): void {
    $tenant = Tenant::create(['name' => 'Northline', 'slug' => 'northline-payables-page', 'base_currency' => 'USD', 'collection_currency' => 'USD']);
    $user = User::create(['tenant_id' => $tenant->id, 'name' => 'Owner', 'email' => 'payables-page@example.test', 'password' => Hash::make('password'), 'role' => 'tenant_owner']);
    app(Tenancy::class)->set($tenant);
    app(CapabilitySeeder::class)->run();
    $user->assignRole('tenant_owner');
    $supplier = Supplier::create(['name' => 'Transit ISP', 'code' => 'TRANSIT']);
    SupplierBill::create([
        'supplier_id' => $supplier->id,
        'reference' => 'BILL-001',
        'period_start' => '2026-08-01',
        'period_end' => '2026-08-31',
        'amount' => 1000,
        'currency' => 'USD',
        'status' => 'open',
    ]);

    $this->actingAs($user)
        ->get(route('reports.supplier-payables', ['as_of' => '2026-08-31', 'supplier_id' => $supplier->id]))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('Reports/SupplierPayables')
            ->where('report.as_of', '2026-08-31')
            ->where('report.summary.outstanding_by_currency.USD', 1000)
            ->where('report.bills.0.reference', 'BILL-001')
            ->where('suppliers.0.code', 'TRANSIT')
        );
});
