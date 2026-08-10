<?php

use App\Actions\CreateInvoice;
use App\Actions\IssueInvoice;
use App\Actions\RequestPortalOtp;
use App\Actions\VerifyPortalOtp;
use App\Models\Customer;
use App\Models\Payment;
use App\Models\Plan;
use App\Models\Tenant;
use App\Support\Tenancy;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('serves customer balance, invoice, payment and invoice PDF resources', function (): void {
    $tenant = Tenant::create(['name' => 'Northline', 'slug' => 'northline', 'base_currency' => 'USD', 'collection_currency' => 'USD']);
    app(Tenancy::class)->set($tenant);
    $customer = Customer::factory()->create(['phone' => '+96170456789']);
    $plan = Plan::factory()->create(['currency' => 'USD']);
    $plan->prices()->create(['currency' => 'USD', 'amount_minor' => 3500, 'effective_from' => now()->subDay()]);
    $invoice = app(IssueInvoice::class)->handle(app(CreateInvoice::class)->handle($customer, $plan));
    $payment = Payment::create([
        'number' => 'PAY-PORTAL-001',
        'customer_id' => $customer->id,
        'invoice_id' => $invoice->id,
        'amount' => 1000,
        'currency' => 'USD',
        'method' => 'card',
        'idempotency_key' => 'portal-resource-payment-001',
        'received_at' => now(),
    ]);
    $payment->allocations()->create(['invoice_id' => $invoice->id, 'amount' => 1000, 'currency' => 'USD']);
    $otp = app(RequestPortalOtp::class)->handle($tenant, $customer->phone);
    $session = app(VerifyPortalOtp::class)->handle($tenant, $otp['challenge']->public_id, $otp['code']);
    $headers = ['Authorization' => 'Bearer '.$session['token']];

    $this->withHeaders($headers)->getJson('/api/v1/portal/northline/me/balance')
        ->assertOk()
        ->assertJsonPath('next_due.invoice_id', $invoice->public_id)
        ->assertJsonPath('next_due.amount', 2500);
    $this->withHeaders($headers)->getJson('/api/v1/portal/northline/me/invoices?per_page=1')
        ->assertOk()
        ->assertJsonPath('data.0.id', $invoice->public_id)
        ->assertJsonPath('data.0.outstanding_amount', 2500);
    $this->withHeaders($headers)->getJson('/api/v1/portal/northline/me/invoices/'.$invoice->public_id)
        ->assertOk()
        ->assertJsonPath('lines.0.description', $plan->name)
        ->assertJsonPath('payments.0.id', $payment->public_id);
    $this->withHeaders($headers)->getJson('/api/v1/portal/northline/me/payments?per_page=1')
        ->assertOk()
        ->assertJsonPath('data.0.id', $payment->public_id)
        ->assertJsonPath('data.0.invoice_id', $invoice->public_id);
    $this->withHeaders($headers)->get('/api/v1/portal/northline/me/invoices/'.$invoice->public_id.'/pdf')->assertDownload($invoice->number.'.pdf');
});

it('does not expose another customer invoice through the portal resource API', function (): void {
    $tenant = Tenant::create(['name' => 'Southline', 'slug' => 'southline', 'base_currency' => 'USD', 'collection_currency' => 'USD']);
    app(Tenancy::class)->set($tenant);
    $customer = Customer::factory()->create(['phone' => '+96170456789']);
    $other = Customer::factory()->create(['phone' => '+96170456788']);
    $plan = Plan::factory()->create();
    $plan->prices()->create(['currency' => 'USD', 'amount_minor' => 3500, 'effective_from' => now()->subDay()]);
    $invoice = app(IssueInvoice::class)->handle(app(CreateInvoice::class)->handle($other, $plan));
    $otp = app(RequestPortalOtp::class)->handle($tenant, $customer->phone);
    $session = app(VerifyPortalOtp::class)->handle($tenant, $otp['challenge']->public_id, $otp['code']);

    $this->withToken($session['token'])->getJson('/api/v1/portal/southline/me/invoices/'.$invoice->public_id)->assertNotFound();
});
