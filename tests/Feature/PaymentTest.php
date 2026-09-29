<?php

use App\Actions\CreateInvoice;
use App\Actions\IssueInvoice;
use App\Actions\RecordPayment;
use App\Actions\ReversePayment;
use App\Enums\PaymentStatus;
use App\Models\Customer;
use App\Models\Payment;
use App\Models\Plan;
use App\Models\Service;
use App\Models\Tenant;
use App\Support\Tenancy;
use Carbon\CarbonImmutable;
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
        ->and($customer->refresh()->balance_amount)->toBe(3500)
        ->and($invoice->outstandingAmount())->toBe(3500);

    $replacement = app(RecordPayment::class)->handle($customer, 3500, 'USD', 'cash', 'payment-002', $invoice);

    expect($replacement->exists)->toBeTrue()
        ->and($customer->refresh()->balance_amount)->toBe(0)
        ->and($invoice->outstandingAmount())->toBe(0)
        ->and($invoice->paymentAllocations()->count())->toBe(2)
        ->and(Payment::count())->toBe(2);
});

it('does not renew a service twice when a paid invoice is reversed and replaced', function (): void {
    $tenant = Tenant::create(['name' => 'Renewal Guard', 'slug' => 'renewal-guard', 'base_currency' => 'USD', 'collection_currency' => 'USD']);
    app(Tenancy::class)->set($tenant);
    $customer = Customer::factory()->create();
    $plan = Plan::factory()->create(['amount_minor' => 3500, 'duration_days' => 30]);
    $plan->prices()->create(['currency' => 'USD', 'amount_minor' => 3500, 'effective_from' => now()->subDay()]);
    $service = Service::factory()->create(['customer_id' => $customer->id, 'plan_id' => $plan->id, 'expires_at' => now()->addDays(10)]);
    $invoice = app(IssueInvoice::class)->handle(app(CreateInvoice::class)->handle($customer, $plan, $service));

    $first = app(RecordPayment::class)->handle($customer, 3500, 'USD', 'cash', 'renewal-payment-001', $invoice);
    $expirationAfterFirstPayment = $service->refresh()->expires_at?->toIso8601String();

    app(ReversePayment::class)->handle($first);
    $replacement = app(RecordPayment::class)->handle($customer, 3500, 'USD', 'cash', 'renewal-payment-002', $invoice);

    expect($replacement->exists)->toBeTrue()
        ->and($service->refresh()->expires_at?->toIso8601String())->toBe($expirationAfterFirstPayment)
        ->and($invoice->refresh()->metadata['renewal_applied_at'])->not->toBeNull();
});

it('recognizes a legacy reversed full allocation before granting another renewal', function (): void {
    $tenant = Tenant::create(['name' => 'Legacy Renewal', 'slug' => 'legacy-renewal', 'base_currency' => 'USD', 'collection_currency' => 'USD']);
    app(Tenancy::class)->set($tenant);
    $customer = Customer::factory()->create();
    $plan = Plan::factory()->create(['amount_minor' => 3500, 'duration_days' => 30]);
    $plan->prices()->create(['currency' => 'USD', 'amount_minor' => 3500, 'effective_from' => now()->subDay()]);
    $service = Service::factory()->create(['customer_id' => $customer->id, 'plan_id' => $plan->id, 'expires_at' => now()->addDays(10)]);
    $invoice = app(IssueInvoice::class)->handle(app(CreateInvoice::class)->handle($customer, $plan, $service));

    $first = app(RecordPayment::class)->handle($customer, 3500, 'USD', 'cash', 'legacy-renewal-payment-001', $invoice);
    $expirationAfterFirstPayment = $service->refresh()->expires_at?->toIso8601String();
    $first->forceFill(['received_at' => now()->subMinute()])->save();
    $allocation = $first->allocations()->firstOrFail();
    $allocation->forceFill(['created_at' => now()->subMinute()])->save();
    $metadata = $invoice->refresh()->metadata ?? [];
    unset($metadata['renewal_applied_at'], $metadata['renewal_applied_service_ids']);
    $invoice->forceFill(['metadata' => $metadata])->save();

    app(ReversePayment::class)->handle($first);
    $replacement = app(RecordPayment::class)->handle($customer, 3500, 'USD', 'cash', 'legacy-renewal-payment-002', $invoice);

    expect($replacement->exists)->toBeTrue()
        ->and($service->refresh()->expires_at?->toIso8601String())->toBe($expirationAfterFirstPayment)
        ->and($invoice->refresh()->metadata['renewal_applied_at'] ?? null)->toBeNull();
});

it('uses invoice allocation links for both loaded and query-backed balances', function (): void {
    $tenant = Tenant::create(['name' => 'Allocation Link', 'slug' => 'allocation-link', 'base_currency' => 'USD', 'collection_currency' => 'USD']);
    app(Tenancy::class)->set($tenant);
    $customer = Customer::factory()->create();
    $plan = Plan::factory()->create(['amount_minor' => 3500]);
    $plan->prices()->create(['currency' => 'USD', 'amount_minor' => 3500, 'effective_from' => now()->subDay()]);
    $invoice = app(IssueInvoice::class)->handle(app(CreateInvoice::class)->handle($customer, $plan));
    $otherInvoice = app(IssueInvoice::class)->handle(app(CreateInvoice::class)->handle($customer, $plan));
    $payment = Payment::create([
        'number' => 'PAY-CROSS-INVOICE-001',
        'customer_id' => $customer->id,
        'invoice_id' => $otherInvoice->id,
        'status' => PaymentStatus::Posted,
        'amount' => 1000,
        'currency' => 'USD',
        'method' => 'cash',
        'idempotency_key' => 'cross-invoice-payment-001',
        'received_at' => now(),
    ]);
    $payment->allocations()->create(['invoice_id' => $invoice->id, 'amount' => 1000, 'currency' => 'USD']);

    expect($invoice->outstandingAmount())->toBe(2500)
        ->and($invoice->load(['paymentAllocations.payment', 'creditNotes'])->outstandingAmount())->toBe(2500);
});

it('fails closed when a legacy reversal ties the allocation timestamp to the second', function (): void {
    $tenant = Tenant::create(['name' => 'Ambiguous Renewal', 'slug' => 'ambiguous-renewal', 'base_currency' => 'USD', 'collection_currency' => 'USD']);
    app(Tenancy::class)->set($tenant);
    $customer = Customer::factory()->create();
    $plan = Plan::factory()->create(['amount_minor' => 3500, 'duration_days' => 30]);
    $plan->prices()->create(['currency' => 'USD', 'amount_minor' => 3500, 'effective_from' => now()->subDay()]);
    $service = Service::factory()->create(['customer_id' => $customer->id, 'plan_id' => $plan->id, 'expires_at' => now()->addDays(10)]);
    $invoice = app(IssueInvoice::class)->handle(app(CreateInvoice::class)->handle($customer, $plan, $service));
    $first = app(RecordPayment::class)->handle($customer, 3500, 'USD', 'cash', 'ambiguous-renewal-payment-001', $invoice);
    $expirationAfterFirstPayment = $service->refresh()->expires_at?->toIso8601String();
    $historicalAt = CarbonImmutable::now()->subMinute()->startOfSecond();
    $first->forceFill(['received_at' => $historicalAt])->save();
    $allocation = $first->allocations()->firstOrFail();
    $allocation->forceFill(['created_at' => $historicalAt])->save();
    $metadata = $invoice->refresh()->metadata ?? [];
    unset($metadata['renewal_applied_at'], $metadata['renewal_applied_service_ids']);
    $invoice->forceFill(['metadata' => $metadata])->save();

    app(ReversePayment::class)->handle($first);
    $first->forceFill(['reversed_at' => $historicalAt])->save();

    expect(fn (): Payment => app(RecordPayment::class)->handle($customer, 3500, 'USD', 'cash', 'ambiguous-renewal-payment-002', $invoice))
        ->toThrow(DomainException::class, 'Reconcile the payment history before recording another payment.')
        ->and($service->refresh()->expires_at?->toIso8601String())->toBe($expirationAfterFirstPayment)
        ->and(Payment::count())->toBe(1)
        ->and($invoice->paymentAllocations()->count())->toBe(1);
});
