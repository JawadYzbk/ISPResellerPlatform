<?php

use App\Actions\CreateInvoice;
use App\Actions\IssueInvoice;
use App\Actions\RecordPayment;
use App\Actions\ReversePayment;
use App\Enums\PaymentStatus;
use App\Models\Customer;
use App\Models\Payment;
use App\Models\Plan;
use App\Models\Tenant;
use App\Support\Tenancy;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('is idempotent and reverses a payment without deleting it', function (): void {
    $tenant = Tenant::create(['name' => 'Northline', 'slug' => 'northline', 'base_currency' => 'USD', 'collection_currency' => 'USD']);
    app(Tenancy::class)->set($tenant);
    $customer = Customer::factory()->create();
    $plan = Plan::factory()->create(['amount_minor' => 3500]);
    $plan->prices()->create(['currency' => 'USD', 'amount_minor' => 3500, 'effective_from' => now()->subDay()]);
    $invoice = app(IssueInvoice::class)->handle(app(CreateInvoice::class)->handle($customer, $plan));

    $first = app(RecordPayment::class)->handle($customer, 3500, 'USD', 'cash', 'payment-001', $invoice);
    $second = app(RecordPayment::class)->handle($customer, 3500, 'USD', 'cash', 'payment-001', $invoice);

    expect($second->id)->toBe($first->id)
        ->and(Payment::count())->toBe(1)
        ->and($customer->refresh()->balance_amount)->toBe(0);

    app(ReversePayment::class)->handle($first);

    expect($first->refresh()->status)->toBe(PaymentStatus::Reversed)
        ->and($customer->refresh()->balance_amount)->toBe(3500);
});
