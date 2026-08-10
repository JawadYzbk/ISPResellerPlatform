<?php

use App\Actions\CreateInvoice;
use App\Actions\IssueInvoice;
use App\Actions\RecordPayment;
use App\Models\Customer;
use App\Models\ExchangeRate;
use App\Models\Plan;
use App\Models\Tenant;
use App\Support\Tenancy;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('records a collection-currency payment with a historical FX snapshot', function (): void {
    $tenant = Tenant::create(['name' => 'Northline', 'slug' => 'northline', 'base_currency' => 'USD', 'collection_currency' => 'LBP']);
    app(Tenancy::class)->set($tenant);
    ExchangeRate::create(['base_currency' => 'USD', 'quote_currency' => 'LBP', 'rate_numerator' => 90_000, 'rate_denominator' => 1, 'effective_from' => now()->subDay(), 'source' => 'manual']);

    $customer = Customer::factory()->create(['balance_currency' => 'USD']);
    $plan = Plan::factory()->create(['amount_minor' => 100]);
    $plan->prices()->create(['currency' => 'USD', 'amount_minor' => 100, 'effective_from' => now()->subDay()]);
    $invoice = app(IssueInvoice::class)->handle(app(CreateInvoice::class)->handle($customer, $plan));

    $payment = app(RecordPayment::class)->handle($customer, 9_000_000, 'LBP', 'cash', 'fx-payment-001', $invoice);

    expect($payment->amount)->toBe(9_000_000)
        ->and($payment->currency)->toBe('LBP')
        ->and($payment->ledger_amount)->toBe(100)
        ->and($payment->ledger_currency)->toBe('USD')
        ->and($payment->base_amount)->toBe(100)
        ->and($payment->fx_rate_numerator)->toBe(1)
        ->and($payment->fx_rate_denominator)->toBe(90_000)
        ->and($payment->fx_rate_overridden)->toBeFalse()
        ->and($payment->allocations()->first()->amount)->toBe(100)
        ->and($payment->allocations()->first()->currency)->toBe('USD')
        ->and($customer->refresh()->balance_amount)->toBe(0);
});

it('records an approved FX override and preserves the operator reason', function (): void {
    $tenant = Tenant::create(['name' => 'Northline', 'slug' => 'northline', 'base_currency' => 'USD', 'collection_currency' => 'LBP']);
    app(Tenancy::class)->set($tenant);

    $customer = Customer::factory()->create(['balance_currency' => 'USD']);
    $plan = Plan::factory()->create(['amount_minor' => 100]);
    $plan->prices()->create(['currency' => 'USD', 'amount_minor' => 100, 'effective_from' => now()->subDay()]);
    $invoice = app(IssueInvoice::class)->handle(app(CreateInvoice::class)->handle($customer, $plan));

    $payment = app(RecordPayment::class)->handle($customer, 10_000_000, 'LBP', 'cash', 'fx-payment-002', $invoice, null, null, 1, 100_000, 'Approved counter rate', 'counter-001');

    expect($payment->base_amount)->toBe(100)
        ->and($payment->fx_rate_numerator)->toBe(1)
        ->and($payment->fx_rate_denominator)->toBe(100_000)
        ->and($payment->fx_rate_overridden)->toBeTrue()
        ->and($payment->fx_override_reason)->toBe('Approved counter rate')
        ->and($payment->reference)->toBe('counter-001')
        ->and($customer->refresh()->balance_amount)->toBe(0);
});
