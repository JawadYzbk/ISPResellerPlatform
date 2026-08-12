<?php

use App\Actions\ExportSupplierPayablesCsv;
use App\Actions\GetSupplierPayablesAging;
use App\Models\Supplier;
use App\Models\SupplierBill;
use App\Models\SupplierPayment;
use App\Models\Tenant;
use App\Support\Tenancy;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('builds an as-of supplier payable queue with aging and supplier totals', function (): void {
    $tenant = Tenant::create(['name' => 'Northline', 'slug' => 'northline', 'base_currency' => 'USD', 'collection_currency' => 'USD']);
    app(Tenancy::class)->set($tenant);
    $supplier = Supplier::create(['name' => 'Transit ISP', 'code' => 'TRANSIT']);
    $otherSupplier = Supplier::create(['name' => 'Backup ISP', 'code' => 'BACKUP']);
    $overdue = SupplierBill::create([
        'supplier_id' => $supplier->id,
        'reference' => 'BILL-OVERDUE',
        'period_start' => '2026-06-01',
        'period_end' => '2026-07-01',
        'amount' => 2000,
        'currency' => 'USD',
        'status' => 'open',
    ]);
    SupplierPayment::create(['supplier_bill_id' => $overdue->id, 'amount' => 500, 'currency' => 'USD', 'paid_at' => '2026-08-01', 'method' => 'bank_transfer']);
    SupplierBill::create([
        'supplier_id' => $supplier->id,
        'reference' => 'BILL-CURRENT',
        'period_start' => '2026-08-01',
        'period_end' => '2026-08-31',
        'amount' => 1000,
        'currency' => 'USD',
        'status' => 'open',
    ]);
    SupplierBill::create([
        'supplier_id' => $otherSupplier->id,
        'reference' => 'BILL-PAID',
        'period_start' => '2026-07-01',
        'period_end' => '2026-07-31',
        'amount' => 700,
        'currency' => 'USD',
        'status' => 'paid',
    ]);
    $paid = SupplierBill::query()->where('reference', 'BILL-PAID')->firstOrFail();
    SupplierPayment::create(['supplier_bill_id' => $paid->id, 'amount' => 700, 'currency' => 'USD', 'paid_at' => '2026-08-02', 'method' => 'bank_transfer']);

    $report = app(GetSupplierPayablesAging::class)->handle(CarbonImmutable::parse('2026-08-31'));

    expect($report['summary'])->toMatchArray([
        'bill_count' => 2,
        'open_bill_count' => 2,
        'billed_by_currency' => ['USD' => 3000],
        'paid_by_currency' => ['USD' => 500],
        'outstanding_by_currency' => ['USD' => 2500],
        'aging_by_currency' => ['USD' => ['current' => 1000, '1_30' => 0, '31_60' => 0, '61_90' => 1500, '90_plus' => 0]],
    ])
        ->and($report['bills'][0]['reference'])->toBe('BILL-OVERDUE')
        ->and($report['bills'][0]['status'])->toBe('partially_paid')
        ->and($report['bills'][0]['outstanding_amount'])->toBe(1500)
        ->and($report['by_supplier'][0]['supplier_code'])->toBe('TRANSIT');
});

it('can include settled bills and filter the payable queue by supplier', function (): void {
    $tenant = Tenant::create(['name' => 'Northline', 'slug' => 'northline-filter', 'base_currency' => 'USD', 'collection_currency' => 'USD']);
    app(Tenancy::class)->set($tenant);
    $supplier = Supplier::create(['name' => 'Transit ISP', 'code' => 'TRANSIT']);
    $bill = SupplierBill::create([
        'supplier_id' => $supplier->id,
        'reference' => 'BILL-PAID',
        'period_start' => '2026-07-01',
        'period_end' => '2026-07-31',
        'amount' => 700,
        'currency' => 'USD',
        'status' => 'paid',
    ]);
    SupplierPayment::create(['supplier_bill_id' => $bill->id, 'amount' => 700, 'currency' => 'USD', 'paid_at' => '2026-08-02', 'method' => 'bank_transfer']);

    $report = app(GetSupplierPayablesAging::class)->handle(CarbonImmutable::parse('2026-08-31'), $supplier->id, true);

    expect($report['include_settled'])->toBeTrue()
        ->and($report['supplier_id'])->toBe($supplier->id)
        ->and($report['summary']['bill_count'])->toBe(1)
        ->and($report['summary']['open_bill_count'])->toBe(0)
        ->and($report['bills'][0]['status'])->toBe('paid');
});

it('exports the exact filtered supplier payable queue', function (): void {
    $tenant = Tenant::create(['name' => 'Northline', 'slug' => 'northline-payables-export', 'base_currency' => 'USD', 'collection_currency' => 'USD']);
    app(Tenancy::class)->set($tenant);
    $supplier = Supplier::create(['name' => 'Transit ISP', 'code' => 'TRANSIT']);
    SupplierBill::create([
        'supplier_id' => $supplier->id,
        'reference' => 'BILL-EXPORT',
        'period_start' => '2026-08-01',
        'period_end' => '2026-08-31',
        'amount' => 1000,
        'currency' => 'USD',
        'status' => 'open',
    ]);

    $csv = app(ExportSupplierPayablesCsv::class)->handle(CarbonImmutable::parse('2026-08-31'), $supplier->id);

    expect($csv)->toContain('as_of,supplier,supplier_code,reference')
        ->toContain('2026-08-31,"Transit ISP",TRANSIT,BILL-EXPORT,2026-08-01,2026-08-31,USD,1000,0,1000,0,current,open');
});
