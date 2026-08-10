<?php

use App\Domain\Payments\WhishPaymentGateway;
use App\Models\Currency;
use App\Models\Customer;
use App\Models\PaymentAttempt;
use App\Models\Tenant;
use App\Support\Tenancy;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use WhishPay\WhishHttpResponse;
use WhishPay\WhishHttpTransport;

uses(TestCase::class, RefreshDatabase::class);

it('converts LBP minor units without floating point arithmetic', function (): void {
    config()->set([
        'services.whish.enabled' => true,
        'services.whish.channel' => 'channel',
        'services.whish.secret' => 'secret',
        'services.whish.website_url' => 'https://app.example.test',
        'services.whish.success_callback_url' => 'https://app.example.test/success',
        'services.whish.failure_callback_url' => 'https://app.example.test/failure',
        'services.whish.success_redirect_url' => 'https://app.example.test/success',
        'services.whish.failure_redirect_url' => 'https://app.example.test/failure',
    ]);
    $transport = new class implements WhishHttpTransport
    {
        /** @var array<string, mixed> */
        public array $payload = [];

        public function send(string $method, string $url, array $headers, ?array $payload, int $timeout): WhishHttpResponse
        {
            $this->payload = $payload ?? [];

            return new WhishHttpResponse(200, '{"status":true,"data":{"collectUrl":"https://pay.example.test/collect"}}');
        }
    };
    app()->instance(WhishHttpTransport::class, $transport);
    $tenant = Tenant::create(['name' => 'Lebanon', 'slug' => 'lebanon', 'base_currency' => 'USD', 'collection_currency' => 'LBP']);
    app(Tenancy::class)->set($tenant);
    $currency = Currency::query()->where('code', 'LBP')->firstOrFail();
    $currency->update(['name' => 'Lebanese pound', 'decimal_digits' => 0, 'is_collection' => true, 'is_active' => true]);
    $customer = Customer::factory()->create();
    $attempt = PaymentAttempt::create([
        'gateway' => 'whish',
        'external_id' => '123456789',
        'customer_id' => $customer->id,
        'amount' => 125000,
        'currency' => $currency->code,
        'idempotency_key' => 'whish:123456789',
        'invoice_reference' => 'INV-100',
    ]);

    $result = app(WhishPaymentGateway::class)->create($attempt);

    expect($result->collectUrl)->toBe('https://pay.example.test/collect')
        ->and($transport->payload['amount'])->toBe('125000')
        ->and($transport->payload['currency'])->toBe('LBP');
});
