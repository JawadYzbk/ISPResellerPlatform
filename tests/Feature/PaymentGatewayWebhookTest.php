<?php

use App\Models\Customer;
use App\Models\Invoice;
use App\Models\Payment;
use App\Models\Tenant;
use App\Support\Tenancy;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('verifies and idempotently records a successful Stripe payment', function (): void {
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
    config(['services.stripe.webhook_secret' => 'whsec_test_123']);
    $payload = json_encode([
        'id' => 'evt_test_001',
        'type' => 'payment_intent.succeeded',
        'data' => ['object' => [
            'id' => 'pi_test_001',
            'amount' => 2500,
            'amount_received' => 2500,
            'currency' => 'usd',
            'metadata' => [
                'tenant_public_id' => $tenant->public_id,
                'customer_public_id' => $customer->public_id,
                'invoice_public_id' => $invoice->public_id,
            ],
        ]],
    ], JSON_THROW_ON_ERROR);
    $timestamp = time();
    $signature = 't='.$timestamp.',v1='.hash_hmac('sha256', $timestamp.'.'.$payload, 'whsec_test_123');

    $first = $this->call('POST', '/api/v1/webhooks/payments/stripe', [], [], [], [
        'HTTP_STRIPE_SIGNATURE' => $signature,
        'CONTENT_TYPE' => 'application/json',
    ], $payload);
    $second = $this->call('POST', '/api/v1/webhooks/payments/stripe', [], [], [], [
        'HTTP_STRIPE_SIGNATURE' => $signature,
        'CONTENT_TYPE' => 'application/json',
    ], $payload);

    $first->assertOk()->assertJsonPath('status', 'processed');
    $second->assertOk()->assertJsonPath('status', 'processed');
    expect(Payment::query()->count())->toBe(1)
        ->and(Payment::query()->firstOrFail()->method)->toBe('gateway')
        ->and(Payment::query()->firstOrFail()->metadata)->toMatchArray([
            'gateway' => 'stripe',
            'gateway_event_id' => 'evt_test_001',
            'gateway_payment_intent_id' => 'pi_test_001',
        ]);
});

it('rejects an unsigned Stripe payment webhook', function (): void {
    config(['services.stripe.webhook_secret' => 'whsec_test_123']);

    $this->withHeaders(['Stripe-Signature' => 't='.time().',v1=invalid'])
        ->post('/api/v1/webhooks/payments/stripe', [], ['Content-Type' => 'application/json'])
        ->assertUnauthorized();
});
