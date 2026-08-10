<?php

use App\Actions\CreateInvoice;
use App\Actions\ExportFinanceReportCsv;
use App\Actions\GetFinanceReport;
use App\Actions\IssueInvoice;
use App\Actions\RecordPayment;
use App\Enums\ServiceStatus;
use App\Models\CashShift;
use App\Models\Customer;
use App\Models\Plan;
use App\Models\Pop;
use App\Models\Router;
use App\Models\Service;
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
        ->and($report['invoiced_by_currency']['USD'])->toBe(3500)
        ->and($report['collected_by_currency']['USD'])->toBe(1000)
        ->and($report['collection_rate_by_currency']['USD'])->toBe(28.57)
        ->and($report['aging_by_currency']['USD']['1_30'])->toBe(2500)
        ->and($report['outstanding_by_currency']['USD'])->toBe(2500)
        ->and($report['revenue_by_plan'][$plan->slug]['USD'])->toBe(3500)
        ->and($report['revenue_by_zone']['unassigned']['USD'])->toBe(3500)
        ->and($report['active_customer_count'])->toBe(1)
        ->and($report['arpu_by_currency']['USD'])->toBe(1000.0)
        ->and($report['top_usage'][0]['service_id'])->toBe($service->public_id)
        ->and($report['top_usage'][0]['total_octets'])->toBe(1000)
        ->and(app(ExportFinanceReportCsv::class)->handle(CarbonImmutable::now()->subDay(), CarbonImmutable::now()->addDay()))
        ->toContain('invoiced_by_currency,USD,3500')
        ->toContain('revenue_by_plan:'.$plan->slug.',USD,3500');
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
    UpstreamLink::create(['pop_id' => $pop->id, 'provider_name' => 'Transit Provider', 'capacity_mbps' => 1000, 'monthly_cost_amount' => 1000, 'currency' => 'USD', 'contract_start' => now()->startOfMonth(), 'contract_end' => now()->endOfMonth()]);

    $from = CarbonImmutable::now()->startOfMonth();
    $to = CarbonImmutable::now()->endOfMonth();
    $report = app(GetFinanceReport::class)->handle($from, $to);

    expect($report['margin_by_pop']['CENTRAL']['revenue_by_currency']['USD'])->toBe(3500)
        ->and($report['margin_by_pop']['CENTRAL']['upstream_cost_by_currency']['USD'])->toBe(1000)
        ->and($report['margin_by_pop']['CENTRAL']['margin_by_currency']['USD'])->toBe(2500)
        ->and($report['collector_performance'][0]['collector'])->toBe('Nadia Collector')
        ->and($report['collector_performance'][0]['payment_count'])->toBe(1)
        ->and($report['collector_performance'][0]['totals_by_currency']['USD'])->toBe(3500)
        ->and(app(ExportFinanceReportCsv::class)->handle($from, $to))->toContain('margin_by_pop:CENTRAL,USD,2500');
});

it('prorates upstream costs separately for each calendar month', function (): void {
    $tenant = Tenant::create(['name' => 'Eastline', 'slug' => 'eastline', 'base_currency' => 'USD', 'collection_currency' => 'USD']);
    app(Tenancy::class)->set($tenant);
    $pop = Pop::create(['name' => 'East POP', 'code' => 'EAST']);
    UpstreamLink::create(['pop_id' => $pop->id, 'provider_name' => 'Transit Provider', 'capacity_mbps' => 1000, 'monthly_cost_amount' => 1000, 'currency' => 'USD', 'contract_start' => '2026-01-01', 'contract_end' => '2026-02-28']);

    $report = app(GetFinanceReport::class)->handle(CarbonImmutable::parse('2026-01-01'), CarbonImmutable::parse('2026-02-28'));

    expect($report['margin_by_pop']['EAST']['upstream_cost_by_currency']['USD'])->toBe(2000);
});
