<?php

use App\Domain\Payments\PaymentGateway;
use App\Domain\Payments\StripePaymentGateway;
use App\Models\Customer;
use App\Models\Invoice;
use App\Models\Tenant;
use App\Support\Tenancy;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;

uses(RefreshDatabase::class);

it('creates a Stripe PaymentIntent with tenant-safe metadata and idempotency', function (): void {
    $tenant = Tenant::create(['name' => 'Northline', 'slug' => 'northline', 'base_currency' => 'USD', 'collection_currency' => 'USD']);
    app(Tenancy::class)->set($tenant);
    $customer = Customer::factory()->create();
    $invoice = Invoice::create([
        'customer_id' => $customer->id,
        'number' => 'INV-001',
        'status' => 'issued',
        'currency' => 'USD',
        'subtotal_amount' => 2500,
        'tax_amount' => 0,
        'total_amount' => 2500,
        'issued_at' => now(),
    ]);
    config([
        'services.payments.driver' => 'stripe',
        'services.stripe.secret' => 'sk_test_123',
        'services.stripe.publishable_key' => 'pk_test_123',
        'services.stripe.endpoint' => 'https://stripe.example.test',
    ]);
    Http::fake([
        'https://stripe.example.test/v1/payment_intents' => Http::response([
            'id' => 'pi_test_001',
            'status' => 'requires_payment_method',
            'amount' => 2500,
            'currency' => 'usd',
            'client_secret' => 'pi_test_001_secret',
            'payment_method_types' => ['card'],
        ]),
    ]);

    $intent = app(StripePaymentGateway::class)->createIntent($customer, $invoice, 2500, 'USD', 'portal-intent-001');

    expect($intent->id)->toBe('pi_test_001')
        ->and($intent->currency)->toBe('USD')
        ->and($intent->payload)->toMatchArray(['client_secret' => 'pi_test_001_secret', 'publishable_key' => 'pk_test_123']);
    Http::assertSent(fn ($request): bool => $request->url() === 'https://stripe.example.test/v1/payment_intents'
        && $request->hasHeader('Idempotency-Key', 'portal-intent-001')
        && $request['amount'] === 2500
        && $request['currency'] === 'usd'
        && $request['metadata[tenant_public_id]'] === $tenant->public_id
        && $request['metadata[customer_public_id]'] === $customer->public_id
        && $request['metadata[invoice_public_id]'] === $invoice->public_id);
});

it('binds the configured payment driver while keeping the null default', function (): void {
    expect(app(PaymentGateway::class))->toBeInstanceOf(\App\Domain\Payments\NullPaymentGateway::class);

    config(['services.payments.driver' => 'stripe']);

    expect(app(PaymentGateway::class))->toBeInstanceOf(StripePaymentGateway::class);
});
