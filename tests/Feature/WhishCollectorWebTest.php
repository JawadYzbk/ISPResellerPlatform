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

it('lets a collector create and poll a Whish QR payment from the web app', function (): void {
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
    app()->instance(WhishHttpTransport::class, new class implements WhishHttpTransport
    {
        public function send(string $method, string $url, array $headers, ?array $payload, int $timeout): WhishHttpResponse
        {
            if (str_ends_with($url, '/payment/collect/status')) {
                return new WhishHttpResponse(200, '{"status":true,"data":{"collectStatus":"success","amount":"12.50","currency":"USD","transactionId":"TX-WEB-001"}}');
            }

            return new WhishHttpResponse(200, '{"status":true,"data":{"collectUrl":"https://pay.example.test/collect"}}');
        }
    });
    $tenant = Tenant::create(['name' => 'Northline', 'slug' => 'northline', 'base_currency' => 'USD', 'collection_currency' => 'USD']);
    app(Tenancy::class)->set($tenant);
    $user = User::create(['tenant_id' => $tenant->id, 'name' => 'Collector', 'email' => 'collector-web-whish@example.test', 'password' => Hash::make('password'), 'role' => 'collector']);
    app(CapabilitySeeder::class)->run();
    $user->assignRole('collector');
    $customer = Customer::factory()->create();

    $this->actingAs($user)
        ->get(route('customers.payments.create', $customer->public_id))
        ->assertOk()
        ->assertInertia(fn ($page) => $page->component('Payments/Create')->where('whishEnabled', true));

    $response = $this->actingAs($user)
        ->post(route('customers.payments.whish.store', $customer->public_id), [
            'amount' => 1250,
            'currency' => 'USD',
            'idempotency_key' => '0198d9a4-0e80-72bb-9ef8-44a7bf6c2191',
        ]);

    $response->assertOk()->assertInertia(fn ($page) => $page
        ->component('Payments/Whish')
        ->where('attempt.status', 'pending')
        ->where('attempt.collect_url', 'https://pay.example.test/collect'));
    app(Tenancy::class)->set($tenant);
    $attempt = PaymentAttempt::query()->where('idempotency_key', '0198d9a4-0e80-72bb-9ef8-44a7bf6c2191')->firstOrFail();

    $this->actingAs($user)
        ->getJson(route('customers.payments.whish.status', [$customer->public_id, $attempt->public_id]))
        ->assertOk()
        ->assertJsonPath('data.status', 'succeeded');

    app(Tenancy::class)->set($tenant);
    expect(Payment::query()->count())->toBe(1)
        ->and($attempt->refresh()->status->value)->toBe('succeeded');
});
