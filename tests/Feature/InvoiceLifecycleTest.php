<?php

use App\Actions\CreateInvoice;
use App\Actions\IssueInvoice;
use App\Actions\VoidInvoice;
use App\Enums\InvoiceStatus;
use App\Models\Customer;
use App\Models\Plan;
use App\Models\Tenant;
use App\Support\Tenancy;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('snapshots the effective price and issues an invoice into the ledger', function (): void {
    $tenant = Tenant::create(['name' => 'Northline', 'slug' => 'northline', 'base_currency' => 'USD', 'collection_currency' => 'USD']);
    app(Tenancy::class)->set($tenant);
    $customer = Customer::factory()->create();
    $plan = Plan::factory()->create(['amount_minor' => 3500]);
    $plan->prices()->create(['currency' => 'USD', 'amount_minor' => 3500, 'effective_from' => now()->subDay()]);

    $invoice = app(CreateInvoice::class)->handle($customer, $plan);
    app(IssueInvoice::class)->handle($invoice);

    expect($invoice->refresh()->status)->toBe(InvoiceStatus::Issued)
        ->and($invoice->lines()->firstOrFail()->price_snapshot['amount_minor'])->toBe(3500)
        ->and($customer->refresh()->balance_amount)->toBe(3500);
});

it('reverses the receivable when an issued invoice is voided', function (): void {
    $tenant = Tenant::create(['name' => 'Southline', 'slug' => 'southline', 'base_currency' => 'USD', 'collection_currency' => 'USD']);
    app(Tenancy::class)->set($tenant);
    $customer = Customer::factory()->create();
    $plan = Plan::factory()->create(['amount_minor' => 1800]);
    $plan->prices()->create(['currency' => 'USD', 'amount_minor' => 1800, 'effective_from' => now()->subDay()]);

    $invoice = app(IssueInvoice::class)->handle(app(CreateInvoice::class)->handle($customer, $plan));
    app(VoidInvoice::class)->handle($invoice);

    expect($invoice->refresh()->status)->toBe(InvoiceStatus::Void)
        ->and($customer->refresh()->balance_amount)->toBe(0);
});
