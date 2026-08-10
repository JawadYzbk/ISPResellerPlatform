<?php

use App\Enums\InvoiceStatus;
use App\Enums\PaymentStatus;
use App\Models\Customer;
use App\Models\Invoice;
use App\Models\InvoiceLine;
use App\Models\Payment;
use App\Models\PaymentAllocation;
use App\Models\Tenant;
use App\Models\User;
use App\Support\Tenancy;
use Database\Seeders\CapabilitySeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;

uses(RefreshDatabase::class);

it('renders tenant-scoped invoice and payment details with posted allocations', function (): void {
    $tenant = Tenant::create(['name' => 'Northline', 'slug' => 'northline', 'base_currency' => 'USD', 'collection_currency' => 'USD']);
    $user = User::create(['tenant_id' => $tenant->id, 'name' => 'Billing manager', 'email' => 'billing-details@example.test', 'password' => Hash::make('password'), 'role' => 'tenant_owner']);
    app(CapabilitySeeder::class)->run();
    app(Tenancy::class)->set($tenant);
    $user->assignRole('tenant_owner');
    $customer = Customer::factory()->create(['balance_currency' => 'USD']);
    $invoice = Invoice::create([
        'number' => 'INV-DETAIL-001',
        'customer_id' => $customer->id,
        'status' => InvoiceStatus::Issued,
        'currency' => 'USD',
        'subtotal_amount' => 3500,
        'tax_amount' => 0,
        'total_amount' => 3500,
        'issued_at' => now()->subDay(),
    ]);
    InvoiceLine::create([
        'invoice_id' => $invoice->id,
        'description' => 'Home 50 · 30 days',
        'quantity' => 1,
        'unit_amount' => 3500,
        'total_amount' => 3500,
        'currency' => 'USD',
    ]);
    $payment = Payment::create([
        'number' => 'RCT-DETAIL-001',
        'customer_id' => $customer->id,
        'invoice_id' => $invoice->id,
        'status' => PaymentStatus::Posted,
        'amount' => 3500,
        'currency' => 'USD',
        'method' => 'cash',
        'idempotency_key' => 'billing-detail-payment',
        'received_at' => now(),
        'actor_id' => $user->id,
        'ledger_amount' => 3500,
        'ledger_currency' => 'USD',
        'base_amount' => 3500,
        'metadata' => ['base_currency' => 'USD'],
        'reference' => 'cash-001',
    ]);
    PaymentAllocation::create(['payment_id' => $payment->id, 'invoice_id' => $invoice->id, 'amount' => 3500, 'currency' => 'USD']);

    $this->actingAs($user)
        ->get(route('billing.invoices.show', $invoice->public_id))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('Billing/InvoiceShow')
            ->where('invoice.number', 'INV-DETAIL-001')
            ->where('invoice.outstanding_amount', 0)
            ->where('invoice.lines.0.description', 'Home 50 · 30 days')
            ->where('invoice.payments.0.number', 'RCT-DETAIL-001')
        );

    app(Tenancy::class)->set($tenant);
    expect(Payment::query()->where('public_id', $payment->public_id)->exists())->toBeTrue();

    $this->actingAs($user)
        ->get(route('billing.payments.show', $payment->public_id))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('Billing/PaymentShow')
            ->where('payment.number', 'RCT-DETAIL-001')
            ->where('payment.invoice.number', 'INV-DETAIL-001')
            ->where('payment.allocations.0.amount', 3500)
            ->where('payment.reference', 'cash-001')
            ->where('canReverse', true)
        );
});

it('does not expose billing details from another tenant', function (): void {
    $tenant = Tenant::create(['name' => 'Northline', 'slug' => 'northline', 'base_currency' => 'USD', 'collection_currency' => 'USD']);
    $otherTenant = Tenant::create(['name' => 'Southline', 'slug' => 'southline', 'base_currency' => 'USD', 'collection_currency' => 'USD']);
    $user = User::create(['tenant_id' => $tenant->id, 'name' => 'Billing manager', 'email' => 'billing-isolation@example.test', 'password' => Hash::make('password'), 'role' => 'tenant_owner']);
    app(CapabilitySeeder::class)->run();
    app(Tenancy::class)->set($otherTenant);
    $customer = Customer::factory()->create();
    $invoice = Invoice::create(['number' => 'INV-SOUTH-DETAIL', 'customer_id' => $customer->id, 'status' => InvoiceStatus::Issued, 'currency' => 'USD', 'total_amount' => 1000]);
    $payment = Payment::create(['number' => 'RCT-SOUTH-DETAIL', 'customer_id' => $customer->id, 'invoice_id' => $invoice->id, 'status' => PaymentStatus::Posted, 'amount' => 1000, 'currency' => 'USD', 'method' => 'cash', 'idempotency_key' => 'billing-detail-other', 'received_at' => now()]);
    app(Tenancy::class)->set($tenant);
    $user->assignRole('tenant_owner');

    $this->actingAs($user)->get(route('billing.invoices.show', $invoice->public_id))->assertNotFound();
    $this->actingAs($user)->get(route('billing.payments.show', $payment->public_id))->assertNotFound();
});
