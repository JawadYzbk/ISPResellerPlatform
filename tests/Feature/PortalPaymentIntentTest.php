<?php

use App\Actions\CreateInvoice;
use App\Actions\CreatePortalPaymentIntent;
use App\Actions\IssueInvoice;
use App\Domain\Payments\PaymentGateway;
use App\Domain\Payments\PaymentIntentResult;
use App\Models\Customer;
use App\Models\Invoice;
use App\Models\Plan;
use App\Models\PortalSession;
use App\Models\Tenant;
use App\Support\Tenancy;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('creates a portal payment intent through the configured gateway seam', function (): void {
    $tenant = Tenant::create(['name' => 'Northline', 'slug' => 'northline', 'base_currency' => 'USD', 'collection_currency' => 'USD']);
    app(Tenancy::class)->set($tenant);
    $customer = Customer::factory()->create();
    $plan = Plan::factory()->create(['currency' => 'USD']);
    $plan->prices()->create(['currency' => 'USD', 'amount_minor' => 3500, 'effective_from' => now()->subDay()]);
    $invoice = app(IssueInvoice::class)->handle(app(CreateInvoice::class)->handle($customer, $plan));
    app()->instance(PaymentGateway::class, new class implements PaymentGateway
    {
        public function createIntent(Customer $customer, Invoice $invoice, int $amount, string $currency, string $idempotencyKey): PaymentIntentResult
        {
            return new PaymentIntentResult('pi_test_001', 'requires_action', $amount, $currency, ['checkout_url' => 'https://pay.example.test/pi_test_001']);
        }
    });

    $intent = app(CreatePortalPaymentIntent::class)->handle($customer, $invoice, 1500, 'portal-intent-001');

    expect($intent->id)->toBe('pi_test_001')
        ->and($intent->amount)->toBe(1500)
        ->and($intent->payload['checkout_url'])->toBe('https://pay.example.test/pi_test_001');

    PortalSession::create(['customer_id' => $customer->id, 'token_hash' => hash('sha256', 'portal_test_token'), 'expires_at' => now()->addHour()]);
    $this->withToken('portal_test_token')->postJson('/api/v1/portal/northline/payments/intent', ['invoice_id' => $invoice->public_id, 'amount' => 1500], ['X-Idempotency-Key' => 'portal-intent-route-001'])
        ->assertCreated()
        ->assertJsonPath('id', 'pi_test_001');
});

it('fails closed when a tenant has no online payment gateway', function (): void {
    $tenant = Tenant::create(['name' => 'Southline', 'slug' => 'southline', 'base_currency' => 'USD', 'collection_currency' => 'USD']);
    app(Tenancy::class)->set($tenant);
    $customer = Customer::factory()->create();
    $plan = Plan::factory()->create(['currency' => 'USD']);
    $plan->prices()->create(['currency' => 'USD', 'amount_minor' => 3500, 'effective_from' => now()->subDay()]);
    $invoice = app(IssueInvoice::class)->handle(app(CreateInvoice::class)->handle($customer, $plan));

    expect(fn (): PaymentIntentResult => app(CreatePortalPaymentIntent::class)->handle($customer, $invoice, 3500, 'portal-intent-002'))
        ->toThrow(DomainException::class, 'No online payment gateway is configured for this tenant.');
});
