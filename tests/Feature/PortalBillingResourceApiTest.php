<?php

use App\Actions\CreateInvoice;
use App\Actions\IssueInvoice;
use App\Actions\RecordPayment;
use App\Actions\RequestPortalOtp;
use App\Actions\ReversePayment;
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
    $this->withHeaders($headers)->getJson('/api/v1/portal/northline/me/profile')->assertOk()->assertJsonPath('public_id', $customer->public_id);
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

it('shows a reversed full payment as unpaid in portal balance and invoice history', function (): void {
    $tenant = Tenant::create(['name' => 'Reversal Portal', 'slug' => 'reversal-portal', 'base_currency' => 'USD', 'collection_currency' => 'USD']);
    app(Tenancy::class)->set($tenant);
    $customer = Customer::factory()->create(['phone' => '+96170456789']);
    $plan = Plan::factory()->create(['currency' => 'USD']);
    $plan->prices()->create(['currency' => 'USD', 'amount_minor' => 3500, 'effective_from' => now()->subDay()]);
    $invoice = app(IssueInvoice::class)->handle(app(CreateInvoice::class)->handle($customer, $plan));
    $payment = app(RecordPayment::class)->handle($customer, 3500, 'USD', 'cash', 'portal-reversal-payment-001', $invoice);
    app(ReversePayment::class)->handle($payment);
    $otp = app(RequestPortalOtp::class)->handle($tenant, $customer->phone);
    $session = app(VerifyPortalOtp::class)->handle($tenant, $otp['challenge']->public_id, $otp['code']);

    $this->withToken($session['token'])->getJson('/api/v1/portal/reversal-portal/me/balance')
        ->assertOk()
        ->assertJsonPath('balance.amount', 3500)
        ->assertJsonPath('next_due.invoice_id', $invoice->public_id)
        ->assertJsonPath('next_due.amount', 3500);
    $this->withToken($session['token'])->getJson('/api/v1/portal/reversal-portal/me/invoices?per_page=1')
        ->assertOk()
        ->assertJsonPath('data.0.id', $invoice->public_id)
        ->assertJsonPath('data.0.allocated_amount', 0)
        ->assertJsonPath('data.0.outstanding_amount', 3500);
});

it('finds an outstanding invoice beyond the first fifty settled portal invoices', function (): void {
    $tenant = Tenant::create(['name' => 'Portal Pagination', 'slug' => 'portal-pagination', 'base_currency' => 'USD', 'collection_currency' => 'USD']);
    app(Tenancy::class)->set($tenant);
    $customer = Customer::factory()->create(['phone' => '+96170456789']);
    $plan = Plan::factory()->create(['currency' => 'USD']);
    $plan->prices()->create(['currency' => 'USD', 'amount_minor' => 3500, 'effective_from' => now()->subDay()]);
    $lastInvoice = null;

    for ($index = 1; $index <= 51; $index++) {
        $lastInvoice = app(IssueInvoice::class)->handle(app(CreateInvoice::class)->handle($customer, $plan));
        $lastInvoice->forceFill(['due_at' => now()->addDays($index)])->save();
        if ($index <= 50) {
            $payment = Payment::create([
                'number' => 'PAY-PORTAL-PAGE-'.str_pad((string) $index, 3, '0', STR_PAD_LEFT),
                'customer_id' => $customer->id,
                'invoice_id' => $lastInvoice->id,
                'status' => 'posted',
                'amount' => 3500,
                'currency' => 'USD',
                'method' => 'cash',
                'idempotency_key' => 'portal-page-payment-'.$index,
                'received_at' => now(),
            ]);
            $payment->allocations()->create(['invoice_id' => $lastInvoice->id, 'amount' => 3500, 'currency' => 'USD']);
        }
    }

    $otp = app(RequestPortalOtp::class)->handle($tenant, $customer->phone);
    $session = app(VerifyPortalOtp::class)->handle($tenant, $otp['challenge']->public_id, $otp['code']);

    $this->withToken($session['token'])->getJson('/api/v1/portal/portal-pagination/me/balance')
        ->assertOk()
        ->assertJsonPath('next_due.invoice_id', $lastInvoice->public_id)
        ->assertJsonPath('next_due.amount', 3500);
});

it('keeps portal invoice cursors on issued invoices and hides drafts by identifier', function (): void {
    $tenant = Tenant::create(['name' => 'Portal Draft Filter', 'slug' => 'portal-draft-filter', 'base_currency' => 'USD', 'collection_currency' => 'USD']);
    app(Tenancy::class)->set($tenant);
    $customer = Customer::factory()->create(['phone' => '+96170456789']);
    $plan = Plan::factory()->create(['currency' => 'USD']);
    $plan->prices()->create(['currency' => 'USD', 'amount_minor' => 3500, 'effective_from' => now()->subDay()]);
    $draft = app(CreateInvoice::class)->handle($customer, $plan);
    $firstIssued = app(IssueInvoice::class)->handle(app(CreateInvoice::class)->handle($customer, $plan));
    $secondIssued = app(IssueInvoice::class)->handle(app(CreateInvoice::class)->handle($customer, $plan));
    $otp = app(RequestPortalOtp::class)->handle($tenant, $customer->phone);
    $session = app(VerifyPortalOtp::class)->handle($tenant, $otp['challenge']->public_id, $otp['code']);

    $firstPage = $this->withToken($session['token'])->getJson('/api/v1/portal/portal-draft-filter/me/invoices?per_page=1')->assertOk();
    $cursor = $firstPage->json('meta.next_cursor');
    expect($cursor)->toBeString();
    $secondPage = $this->withToken($session['token'])->getJson('/api/v1/portal/portal-draft-filter/me/invoices?per_page=1&cursor='.urlencode($cursor))->assertOk();
    $pageIds = [$firstPage->json('data.0.id'), $secondPage->json('data.0.id')];

    expect($pageIds)->toContain($firstIssued->public_id)
        ->toContain($secondIssued->public_id)
        ->not->toContain($draft->public_id)
        ->and($pageIds[0])->not->toBe($pageIds[1]);
    $this->withToken($session['token'])->getJson('/api/v1/portal/portal-draft-filter/me/invoices/'.$draft->public_id)->assertNotFound();
    $this->withToken($session['token'])->get('/api/v1/portal/portal-draft-filter/me/invoices/'.$draft->public_id.'/pdf')->assertNotFound();
});
