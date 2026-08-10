<?php

use App\Actions\CreateInvoice;
use App\Actions\ExportFinanceReportCsv;
use App\Actions\GetFinanceReport;
use App\Actions\IssueInvoice;
use App\Actions\RecordPayment;
use App\Models\Customer;
use App\Models\Plan;
use App\Models\Tenant;
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
    app(RecordPayment::class)->handle($customer, 1000, 'USD', 'cash', 'report-payment-001', $invoice);

    $report = app(GetFinanceReport::class)->handle(CarbonImmutable::now()->subDay(), CarbonImmutable::now()->addDay());

    expect($report['invoice_count'])->toBe(1)
        ->and($report['payment_count'])->toBe(1)
        ->and($report['invoiced_by_currency']['USD'])->toBe(3500)
        ->and($report['collected_by_currency']['USD'])->toBe(1000)
        ->and(app(ExportFinanceReportCsv::class)->handle(CarbonImmutable::now()->subDay(), CarbonImmutable::now()->addDay()))
        ->toContain('invoiced_by_currency,USD,3500');
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

    expect($response->streamedContent())->toContain('metric,currency,amount_minor');
});
