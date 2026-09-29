<?php

use App\Actions\CreateInvoice;
use App\Actions\ExportFinanceReportCsv;
use App\Actions\GetFinanceReport;
use App\Actions\IssueInvoice;
use App\Actions\RecordPayment;
use App\Enums\ServiceStatus;
use App\Models\CashShift;
use App\Models\CreditNote;
use App\Models\Customer;
use App\Models\Payment;
use App\Models\Plan;
use App\Models\Pop;
use App\Models\Router;
use App\Models\Service;
use App\Models\Supplier;
use App\Models\SupplierBill;
use App\Models\SupplierPayment;
use App\Models\Tenant;
use App\Models\UpstreamLink;
use App\Models\UsageDaily;
use App\Models\User;
use App\Support\Tenancy;
use Carbon\CarbonImmutable;
use Database\Seeders\CapabilitySeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;

uses(RefreshDatabase::class);

it('reconciles issued revenue and posted collections by currency', function (): void {
    $tenant = Tenant::create(['name' => 'Northline', 'slug' => 'northline', 'base_currency' => 'USD', 'collection_currency' => 'USD']);
    app(Tenancy::class)->set($tenant);
    $customer = Customer::factory()->create();
    $plan = Plan::factory()->create(['amount_minor' => 3500]);
    $plan->prices()->create(['currency' => 'USD', 'amount_minor' => 3500, 'effective_from' => now()->subDay()]);
    $invoice = app(IssueInvoice::class)->handle(app(CreateInvoice::class)->handle($customer, $plan));
    $invoice->update(['due_at' => now()->subDays(10)]);
    app(RecordPayment::class)->handle($customer, 1000, 'USD', 'cash', 'report-payment-001', $invoice);
    $service = Service::factory()->create(['customer_id' => $customer->id, 'status' => ServiceStatus::Active]);
    UsageDaily::create(['service_id' => $service->id, 'usage_date' => now()->toDateString(), 'input_octets' => 200, 'output_octets' => 800, 'total_octets' => 1000, 'rolled_up_at' => now()]);

    $report = app(GetFinanceReport::class)->handle(CarbonImmutable::now()->subDay(), CarbonImmutable::now()->addDay());

    expect($report['invoice_count'])->toBe(1)
        ->and($report['payment_count'])->toBe(1)
        ->and($report['gross_invoiced_by_currency']['USD'])->toBe(3500)
        ->and($report['credited_by_currency']['USD'] ?? 0)->toBe(0)
        ->and($report['net_invoiced_by_currency']['USD'])->toBe(3500)
        ->and($report['collected_by_currency']['USD'])->toBe(1000)
        ->and($report['collection_rate_by_currency']['USD'])->toBe(28.57)
        ->and($report['collection_trend'][0]['gross_invoiced_by_currency']['USD'])->toBe(3500)
        ->and($report['collection_trend'][0]['net_invoiced_by_currency']['USD'])->toBe(3500)
        ->and($report['collection_trend'][0]['collected_by_currency']['USD'])->toBe(1000)
        ->and($report['cash_reconciliation']['closed_shift_count'])->toBe(0)
        ->and($report['aging_by_currency']['USD']['1_30'])->toBe(2500)
        ->and($report['outstanding_by_currency']['USD'])->toBe(2500)
        ->and($report['gross_revenue_by_plan'][$plan->slug]['USD'])->toBe(3500)
        ->and($report['gross_revenue_by_zone']['unassigned']['USD'])->toBe(3500)
        ->and($report['current_active_customer_count'])->toBe(1)
        ->and($report['cash_collected_per_current_active_customer_by_currency']['USD'])->toBe(1000.0)
        ->and($report['top_usage'][0]['service_id'])->toBe($service->public_id)
        ->and($report['top_usage'][0]['total_octets'])->toBe(1000)
        ->and(app(ExportFinanceReportCsv::class)->handle(CarbonImmutable::now()->subDay(), CarbonImmutable::now()->addDay()))
        ->toContain('gross_invoiced_by_currency,USD,3500')
        ->toContain('gross_revenue_by_plan:'.$plan->slug.',USD,3500');
});

it('streams the finance report as CSV for an authorised operator', function (): void {
    $tenant = Tenant::create(['name' => 'Southline', 'slug' => 'southline', 'base_currency' => 'USD', 'collection_currency' => 'USD']);
    app(Tenancy::class)->set($tenant);
    $user = User::create(['tenant_id' => $tenant->id, 'name' => 'Reports', 'email' => 'reports@example.test', 'password' => Hash::make('password'), 'role' => 'support_agent']);
    app(CapabilitySeeder::class)->run();
    $user->assignRole('support_agent');
    $user->givePermissionTo('reports.finance');

    $response = $this->actingAs($user)->get('/reports/finance?format=csv&from=2026-08-01&to=2026-08-10')
        ->assertOk()
        ->assertHeader('content-type', 'text/csv; charset=UTF-8')
        ->assertStreamed();

    expect($response->streamedContent())->toContain('metric,currency,value');

    $xlsx = $this->actingAs($user)->get('/reports/finance?format=xlsx&from=2026-08-01&to=2026-08-10')
        ->assertOk()
        ->assertHeader('content-type', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet')
        ->assertStreamed();

    expect(substr($xlsx->streamedContent(), 0, 2))->toBe('PK');
});

it('reports supplier payables, payments and aging by currency', function (): void {
    $tenant = Tenant::create(['name' => 'Supplier Finance', 'slug' => 'supplier-finance', 'base_currency' => 'USD', 'collection_currency' => 'USD']);
    app(Tenancy::class)->set($tenant);
    $supplier = Supplier::create(['name' => 'Transit ISP', 'code' => 'TRANSIT']);
    $bill = SupplierBill::create([
        'supplier_id' => $supplier->id,
        'reference' => 'BILL-001',
        'period_start' => '2026-07-01',
        'period_end' => '2026-07-31',
        'amount' => 5000,
        'currency' => 'USD',
        'status' => 'open',
    ]);
    SupplierPayment::create([
        'supplier_bill_id' => $bill->id,
        'amount' => 2000,
        'currency' => 'USD',
        'paid_at' => '2026-08-05',
        'method' => 'bank_transfer',
    ]);

    $report = app(GetFinanceReport::class)->handle(
        CarbonImmutable::parse('2026-08-01'),
        CarbonImmutable::parse('2026-08-31'),
    );

    expect($report['supplier_payables'])->toMatchArray([
        'bill_count' => 0,
        'payment_count' => 1,
        'billed_by_currency' => [],
        'paid_by_currency' => ['USD' => 2000],
        'outstanding_by_currency' => ['USD' => 3000],
        'aging_by_currency' => ['USD' => ['current' => 0, '1_30' => 0, '31_60' => 3000, '61_90' => 0, '90_plus' => 0]],
    ]);
    expect(app(ExportFinanceReportCsv::class)->handle(
        CarbonImmutable::parse('2026-08-01'),
        CarbonImmutable::parse('2026-08-31'),
    ))->toContain('supplier_payables_paid_by_currency,USD,2000')->toContain('supplier_payables_aging_31_60,USD,3000');
});

it('reports POP margin and collector performance from posted records', function (): void {
    $tenant = Tenant::create(['name' => 'Westline', 'slug' => 'westline', 'base_currency' => 'USD', 'collection_currency' => 'USD']);
    app(Tenancy::class)->set($tenant);
    $collector = User::create(['tenant_id' => $tenant->id, 'name' => 'Nadia Collector', 'email' => 'nadia-report@example.test', 'password' => Hash::make('password'), 'role' => 'collector']);
    $shift = CashShift::create(['user_id' => $collector->id, 'status' => 'open', 'opened_at' => now()]);
    $pop = Pop::create(['name' => 'Central POP', 'code' => 'CENTRAL']);
    $router = Router::create(['pop_id' => $pop->id, 'name' => 'Core-01', 'host' => '192.0.2.20', 'username' => 'api', 'password_encrypted' => 'secret']);
    $service = Service::factory()->create(['router_id' => $router->id, 'status' => ServiceStatus::Active]);
    $service->plan->prices()->create(['currency' => 'USD', 'amount_minor' => 3500, 'effective_from' => now()->subDay()]);
    $invoice = app(IssueInvoice::class)->handle(app(CreateInvoice::class)->handle($service->customer, $service->plan, $service));
    app(RecordPayment::class)->handle($service->customer, 3500, 'USD', 'cash', 'report-collector-001', $invoice, $collector, $shift);
    $shift->update(['status' => 'closed', 'closed_at' => now(), 'system_totals' => ['USD' => 3500], 'declared_totals' => ['USD' => 3400], 'variance' => true]);
    UpstreamLink::create(['pop_id' => $pop->id, 'provider_name' => 'Transit Provider', 'capacity_mbps' => 1000, 'monthly_cost_amount' => 1000, 'currency' => 'USD', 'contract_start' => now()->startOfMonth(), 'contract_end' => now()->endOfMonth()]);

    $from = CarbonImmutable::now()->startOfMonth();
    $to = CarbonImmutable::now()->endOfMonth();
    $report = app(GetFinanceReport::class)->handle($from, $to);

    expect($report['margin_by_pop']['CENTRAL']['gross_revenue_by_currency']['USD'])->toBe(3500)
        ->and($report['margin_by_pop']['CENTRAL']['upstream_cost_by_currency']['USD'])->toBe(1000)
        ->and($report['margin_by_pop']['CENTRAL']['gross_margin_by_currency']['USD'])->toBe(2500)
        ->and($report['collector_performance'][0]['collector'])->toBe('Nadia Collector')
        ->and($report['collector_performance'][0]['payment_count'])->toBe(1)
        ->and($report['collector_performance'][0]['totals_by_currency']['USD'])->toBe(3500)
        ->and($report['cash_reconciliation']['variance_shift_count'])->toBe(1)
        ->and($report['cash_reconciliation']['variance_by_currency']['USD'])->toBe(-100)
        ->and(app(ExportFinanceReportCsv::class)->handle($from, $to))
        ->toContain('gross_margin_by_pop:CENTRAL,USD,2500')
        ->toContain('cash_variance_by_currency,USD,-100');
});

it('prorates upstream costs separately for each calendar month', function (): void {
    $tenant = Tenant::create(['name' => 'Eastline', 'slug' => 'eastline', 'base_currency' => 'USD', 'collection_currency' => 'USD']);
    app(Tenancy::class)->set($tenant);
    $pop = Pop::create(['name' => 'East POP', 'code' => 'EAST']);
    UpstreamLink::create(['pop_id' => $pop->id, 'provider_name' => 'Transit Provider', 'capacity_mbps' => 1000, 'monthly_cost_amount' => 1000, 'currency' => 'USD', 'contract_start' => '2026-01-01', 'contract_end' => '2026-02-28']);

    $report = app(GetFinanceReport::class)->handle(CarbonImmutable::parse('2026-01-01'), CarbonImmutable::parse('2026-02-28'));

    expect($report['margin_by_pop']['EAST']['upstream_cost_by_currency']['USD'])->toBe(2000);
});

it('keeps finance report collections and aging aligned to the report as-of date', function (): void {
    $tenant = Tenant::create(['name' => 'As Of Finance', 'slug' => 'as-of-finance', 'base_currency' => 'USD', 'collection_currency' => 'USD']);
    app(Tenancy::class)->set($tenant);
    $customer = Customer::factory()->create();
    $plan = Plan::factory()->create(['amount_minor' => 3500]);
    $plan->prices()->create(['currency' => 'USD', 'amount_minor' => 3500, 'effective_from' => now()->subYear()]);
    $firstInvoice = app(IssueInvoice::class)->handle(app(CreateInvoice::class)->handle($customer, $plan));
    $secondInvoice = app(IssueInvoice::class)->handle(app(CreateInvoice::class)->handle($customer, $plan));
    $firstInvoice->forceFill(['issued_at' => '2026-08-01', 'due_at' => '2026-08-01'])->save();
    $secondInvoice->forceFill(['issued_at' => '2026-08-02', 'due_at' => '2026-08-02'])->save();
    $reversedLater = Payment::create([
        'number' => 'PAY-ASOF-REVERSED',
        'customer_id' => $customer->id,
        'invoice_id' => $firstInvoice->id,
        'status' => 'reversed',
        'amount' => 3500,
        'currency' => 'USD',
        'method' => 'cash',
        'idempotency_key' => 'asof-reversed-payment-001',
        'received_at' => '2026-08-10',
        'reversed_at' => '2026-08-20',
    ]);
    $firstAllocation = $reversedLater->allocations()->create(['invoice_id' => $firstInvoice->id, 'amount' => 3500, 'currency' => 'USD']);
    $firstAllocation->forceFill(['created_at' => '2026-08-10'])->save();
    $receivedLater = Payment::create([
        'number' => 'PAY-ASOF-FUTURE',
        'customer_id' => $customer->id,
        'invoice_id' => $secondInvoice->id,
        'status' => 'posted',
        'amount' => 3500,
        'currency' => 'USD',
        'method' => 'cash',
        'idempotency_key' => 'asof-future-payment-001',
        'received_at' => '2026-09-05',
    ]);
    $secondAllocation = $receivedLater->allocations()->create(['invoice_id' => $secondInvoice->id, 'amount' => 3500, 'currency' => 'USD']);
    $secondAllocation->forceFill(['created_at' => '2026-09-05'])->save();

    $beforeReversal = app(GetFinanceReport::class)->handle(CarbonImmutable::parse('2026-08-01'), CarbonImmutable::parse('2026-08-15'));
    $afterReversal = app(GetFinanceReport::class)->handle(CarbonImmutable::parse('2026-08-01'), CarbonImmutable::parse('2026-08-31'));

    expect($beforeReversal['collected_by_currency']['USD'])->toBe(3500)
        ->and($beforeReversal['outstanding_by_currency']['USD'])->toBe(3500)
        ->and($afterReversal['collected_by_currency'])->toBe([])
        ->and($afterReversal['outstanding_by_currency']['USD'])->toBe(7000);
});

it('reports net period credits below zero without assigning credits to gross dimensions or tax', function (): void {
    $tenant = Tenant::create(['name' => 'Negative Net Finance', 'slug' => 'negative-net-finance', 'base_currency' => 'USD', 'collection_currency' => 'USD']);
    app(Tenancy::class)->set($tenant);
    $customer = Customer::factory()->create();
    $plan = Plan::factory()->create(['amount_minor' => 3500]);
    $plan->prices()->create(['currency' => 'USD', 'amount_minor' => 3500, 'effective_from' => now()->subYear()]);
    $priorInvoice = app(IssueInvoice::class)->handle(app(CreateInvoice::class)->handle($customer, $plan));
    $priorInvoice->forceFill(['issued_at' => '2026-07-31'])->save();
    $secondPriorInvoice = app(IssueInvoice::class)->handle(app(CreateInvoice::class)->handle($customer, $plan));
    $secondPriorInvoice->forceFill(['issued_at' => '2026-07-30'])->save();
    $currentInvoice = app(IssueInvoice::class)->handle(app(CreateInvoice::class)->handle($customer, $plan));
    $currentInvoice->forceFill(['issued_at' => '2026-08-05', 'subtotal_amount' => 3150, 'tax_amount' => 350])->save();
    CreditNote::create([
        'invoice_id' => $priorInvoice->id,
        'customer_id' => $customer->id,
        'number' => 'CN-FINANCE-001',
        'amount' => 3500,
        'currency' => 'USD',
        'status' => 'issued',
        'reason' => 'Period credit',
        'issued_at' => '2026-08-10',
    ]);
    CreditNote::create([
        'invoice_id' => $secondPriorInvoice->id,
        'customer_id' => $customer->id,
        'number' => 'CN-FINANCE-002',
        'amount' => 3500,
        'currency' => 'USD',
        'status' => 'issued',
        'reason' => 'Period credit',
        'issued_at' => '2026-08-10',
    ]);

    $report = app(GetFinanceReport::class)->handle(CarbonImmutable::parse('2026-08-01'), CarbonImmutable::parse('2026-08-31'));
    $csv = app(ExportFinanceReportCsv::class)->handle(CarbonImmutable::parse('2026-08-01'), CarbonImmutable::parse('2026-08-31'));

    expect($report['gross_invoiced_by_currency']['USD'])->toBe(3500)
        ->and($report['credited_by_currency']['USD'])->toBe(7000)
        ->and($report['net_invoiced_by_currency']['USD'])->toBe(-3500)
        ->and($report['collection_rate_by_currency']['USD'])->toBeNull()
        ->and($report['collection_trend'][1]['credited_by_currency']['USD'])->toBe(7000)
        ->and($report['tax_by_currency']['USD'])->toBe(350)
        ->and($report['gross_revenue_by_plan'][$plan->slug]['USD'])->toBe(3500)
        ->and($csv)->toContain('net_invoiced_by_currency,USD,-3500')
        ->toContain('trend_credited:2026-08-10,USD,7000');
});
