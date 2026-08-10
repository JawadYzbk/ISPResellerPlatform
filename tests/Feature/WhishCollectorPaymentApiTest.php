<?php

use App\Models\Customer;
use App\Models\Payment;
use App\Models\PaymentAttempt;
use App\Models\Tenant;
use App\Models\User;
use App\Support\Tenancy;
use Database\Seeders\CapabilitySeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use WhishPay\WhishHttpResponse;
use WhishPay\WhishHttpTransport;

uses(RefreshDatabase::class);

it('lets a collector create an idempotent Whish QR payment', function (): void {
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
        public int $calls = 0;

        public function send(string $method, string $url, array $headers, ?array $payload, int $timeout): WhishHttpResponse
        {
            $this->calls++;

            if (str_ends_with($url, '/payment/collect/status')) {
                return new WhishHttpResponse(200, '{"status":true,"data":{"collectStatus":"success","amount":"12.50","currency":"USD","transactionId":"TX-QR-001"}}');
            }

            return new WhishHttpResponse(200, '{"status":true,"data":{"collectUrl":"https://pay.example.test/collect"}}');
        }
    };
    app()->instance(WhishHttpTransport::class, $transport);
    $tenant = Tenant::create(['name' => 'Northline', 'slug' => 'northline', 'base_currency' => 'USD', 'collection_currency' => 'USD']);
    app(Tenancy::class)->set($tenant);
    $user = User::create(['tenant_id' => $tenant->id, 'name' => 'Collector', 'email' => 'collector-qr@example.test', 'password' => Hash::make('password'), 'role' => 'collector']);
    app(CapabilitySeeder::class)->run();
    $user->assignRole('collector');
    $customer = Customer::factory()->create();
    $token = $user->createToken('collector', ['api', 'staff:collector'])->plainTextToken;
    $payload = ['customer_id' => $customer->public_id, 'amount' => 1250, 'currency' => 'USD'];

    $first = $this->withToken($token)->withHeader('X-Idempotency-Key', 'whish-qr-001')->postJson('/api/v1/collector/payments/whish', $payload);
    $second = $this->withToken($token)->withHeader('X-Idempotency-Key', 'whish-qr-001')->postJson('/api/v1/collector/payments/whish', $payload);
    $status = $this->withToken($token)->getJson('/api/v1/collector/payments/whish/'.$first->json('data.id'));

    $first->assertCreated()
        ->assertJsonPath('data.status', 'pending')
        ->assertJsonPath('data.collect_url', 'https://pay.example.test/collect')
        ->assertJsonPath('data.qr_code.format', 'svg');
    $second->assertCreated()->assertJsonPath('data.id', $first->json('data.id'));
    $status->assertOk()->assertJsonPath('data.status', 'succeeded');
    expect($first->json('data.qr_code.data_uri'))->toStartWith('data:image/svg+xml')
        ->and($transport->calls)->toBe(2)
        ->and(PaymentAttempt::withoutGlobalScopes()->count())->toBe(1)
        ->and(Payment::withoutGlobalScopes()->count())->toBe(1);
});
