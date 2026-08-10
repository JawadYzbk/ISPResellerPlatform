<?php

use App\Actions\CreateInvoice;
use App\Actions\GetFinanceReport;
use App\Actions\IssueInvoice;
use App\Actions\RecordPayment;
use App\Models\Customer;
use App\Models\Plan;
use App\Models\Tenant;
use App\Support\Tenancy;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;

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
        ->and($report['collected_by_currency']['USD'])->toBe(1000);
});
