<?php

use App\Models\Customer;
use App\Models\Payment;
use App\Models\PaymentAttempt;
use App\Models\Tenant;
use App\Models\User;
use App\Support\Tenancy;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use WhishPay\WhishHttpResponse;
use WhishPay\WhishHttpTransport;

uses(RefreshDatabase::class);

it('verifies Whish status server-side and posts a successful payment once', function (): void {
    config()->set([
        'services.whish.enabled' => true,
        'services.whish.channel' => 'channel',
        'services.whish.secret' => 'secret',
        'services.whish.website_url' => 'https://app.example.test',
    ]);
    $transport = new class implements WhishHttpTransport
    {
        public int $calls = 0;

        public function send(string $method, string $url, array $headers, ?array $payload, int $timeout): WhishHttpResponse
        {
            $this->calls++;

            return new WhishHttpResponse(200, '{"status":true,"data":{"collectStatus":"success","transactionId":"TX-100"}}');
        }
    };
    app()->instance(WhishHttpTransport::class, $transport);
    $tenant = Tenant::create(['name' => 'Northline', 'slug' => 'northline', 'base_currency' => 'USD', 'collection_currency' => 'USD']);
    app(Tenancy::class)->set($tenant);
    $actor = User::create(['tenant_id' => $tenant->id, 'name' => 'Collector', 'email' => 'collector-whish@example.test', 'password' => Hash::make('password'), 'role' => 'collector']);
    $customer = Customer::factory()->create();
    $attempt = PaymentAttempt::create([
        'gateway' => 'whish',
        'external_id' => '123456789',
        'customer_id' => $customer->id,
        'actor_id' => $actor->id,
        'amount' => 1250,
        'currency' => 'USD',
        'idempotency_key' => 'whish:123456789',
        'invoice_reference' => 'COL-123456789',
        'collect_url' => 'https://pay.example.test/collect',
    ]);
    app(Tenancy::class)->clear();

    $first = $this->getJson('/api/v1/webhooks/payments/whish/success?externalId=123456789&currency=USD');
    $second = $this->getJson('/api/v1/webhooks/payments/whish/failure?externalId=123456789&currency=USD');

    $first->assertOk()->assertJsonPath('data.status', 'succeeded');
    $second->assertOk()->assertJsonPath('data.status', 'succeeded');
    expect($transport->calls)->toBe(1)
        ->and(Payment::withoutGlobalScopes()->count())->toBe(1)
        ->and($attempt->withoutGlobalScopes()->findOrFail($attempt->id)->status->value)->toBe('succeeded');
});

it('does not post a payment when the Whish callback status amount is tampered with', function (): void {
    config()->set([
        'services.whish.enabled' => true,
        'services.whish.channel' => 'channel',
        'services.whish.secret' => 'secret',
        'services.whish.website_url' => 'https://app.example.test',
    ]);
    app()->instance(WhishHttpTransport::class, new class implements WhishHttpTransport
    {
        public function send(string $method, string $url, array $headers, ?array $payload, int $timeout): WhishHttpResponse
        {
            return new WhishHttpResponse(200, '{"status":true,"data":{"collectStatus":"success","amount":"99.99","currency":"USD","transactionId":"TX-101"}}');
        }
    });
    $tenant = Tenant::create(['name' => 'Southline', 'slug' => 'southline', 'base_currency' => 'USD', 'collection_currency' => 'USD']);
    app(Tenancy::class)->set($tenant);
    $customer = Customer::factory()->create();
    $attempt = PaymentAttempt::create([
        'gateway' => 'whish',
        'external_id' => '987654321',
        'customer_id' => $customer->id,
        'amount' => 1250,
        'currency' => 'USD',
        'idempotency_key' => 'whish:987654321',
        'invoice_reference' => 'COL-987654321',
    ]);
    app(Tenancy::class)->clear();

    $this->getJson('/api/v1/webhooks/payments/whish/success?externalId=987654321&currency=USD')->assertStatus(422);

    expect(Payment::withoutGlobalScopes()->count())->toBe(0)
        ->and($attempt->withoutGlobalScopes()->findOrFail($attempt->id)->status->value)->toBe('failed');
});
