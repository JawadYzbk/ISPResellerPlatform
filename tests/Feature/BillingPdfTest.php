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

it('downloads tenant-scoped invoice and receipt PDFs', function (): void {
    $tenant = Tenant::create(['name' => 'Northline', 'slug' => 'northline', 'base_currency' => 'USD', 'collection_currency' => 'USD']);
    $user = User::create(['tenant_id' => $tenant->id, 'name' => 'Billing manager', 'email' => 'billing-pdf@example.test', 'password' => Hash::make('password'), 'role' => 'tenant_owner']);
    app(CapabilitySeeder::class)->run();
    app(Tenancy::class)->set($tenant);
    $user->assignRole('tenant_owner');
    $customer = Customer::factory()->create(['balance_currency' => 'USD']);
    $invoice = Invoice::create([
        'number' => 'INV-PDF-001',
        'customer_id' => $customer->id,
        'status' => InvoiceStatus::Issued,
        'currency' => 'USD',
        'subtotal_amount' => 3500,
        'tax_amount' => 0,
        'total_amount' => 3500,
        'issued_at' => now()->subDay(),
    ]);
    InvoiceLine::create(['invoice_id' => $invoice->id, 'description' => 'Home plan', 'quantity' => 1, 'unit_amount' => 3500, 'total_amount' => 3500, 'currency' => 'USD']);
    $payment = Payment::create([
        'number' => 'RCT-PDF-001',
        'customer_id' => $customer->id,
        'invoice_id' => $invoice->id,
        'status' => PaymentStatus::Posted,
        'amount' => 3500,
        'currency' => 'USD',
        'method' => 'cash',
        'idempotency_key' => 'billing-pdf-payment',
        'received_at' => now(),
        'actor_id' => $user->id,
    ]);
    PaymentAllocation::create(['payment_id' => $payment->id, 'invoice_id' => $invoice->id, 'amount' => 3500, 'currency' => 'USD']);

    $this->actingAs($user)
        ->get(route('billing.invoices.pdf', $invoice->public_id))
        ->assertOk()
        ->assertHeader('Content-Type', 'application/pdf')
        ->assertHeader('Content-Disposition', 'attachment; filename=INV-PDF-001.pdf');

    $this->actingAs($user)
        ->get(route('billing.payments.pdf', $payment->public_id))
        ->assertOk()
        ->assertHeader('Content-Type', 'application/pdf')
        ->assertHeader('Content-Disposition', 'attachment; filename=RCT-PDF-001.pdf');
});
