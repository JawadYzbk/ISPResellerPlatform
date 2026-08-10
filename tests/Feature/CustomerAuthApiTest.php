<?php

use App\Actions\RequestPortalOtp;
use App\Models\Customer;
use App\Models\PortalOtpChallenge;
use App\Models\PortalSession;
use App\Models\Tenant;
use App\Support\Tenancy;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('keeps customer OTP requests non-enumerating and issues a device-bound portal token', function (): void {
    $tenant = Tenant::create(['name' => 'Northline', 'slug' => 'northline', 'base_currency' => 'USD', 'collection_currency' => 'USD']);
    app(Tenancy::class)->set($tenant);
    $customer = Customer::factory()->create(['phone' => '+96170456789']);

    $known = $this->postJson('/api/v1/auth/customer/otp/request', ['phone' => $customer->phone]);
    $unknown = $this->postJson('/api/v1/auth/customer/otp/request', ['phone' => '+96170999999']);

    $known->assertOk()->assertExactJson(['expires_in' => 300, 'resend_after' => 60]);
    $unknown->assertOk()->assertExactJson(['expires_in' => 300, 'resend_after' => 60]);

    $challenges = app(Tenancy::class)->run($tenant, fn () => PortalOtpChallenge::query()->latest('id')->get());
    expect($challenges)->toHaveCount(2)
        ->and($challenges->last()->customer_id)->toBe($customer->id)
        ->and($challenges->first()->customer_id)->toBeNull();
});

it('accepts the documented phone-code customer login and resolves root me routes from the session tenant', function (): void {
    $tenant = Tenant::create(['name' => 'Southline', 'slug' => 'southline', 'base_currency' => 'USD', 'collection_currency' => 'USD']);
    app(Tenancy::class)->set($tenant);
    $customer = Customer::factory()->create(['phone' => '+96170456788']);
    $otp = app(RequestPortalOtp::class)->handle($tenant, $customer->phone);

    $response = $this->withHeaders(['X-Tenant-Slug' => $tenant->slug])->postJson('/api/v1/auth/customer/otp/verify', [
        'phone' => $customer->phone,
        'code' => $otp['code'],
        'device_id' => 'customer-phone-001',
    ]);

    $response->assertOk()->assertJsonPath('type', 'Bearer')->assertJsonStructure(['token', 'expires_at', 'type']);
    $session = app(Tenancy::class)->run($tenant, fn (): PortalSession => PortalSession::query()->where('customer_id', $customer->id)->latest('id')->firstOrFail());
    expect($session->device_id)->toBe('customer-phone-001');

    $this->withToken((string) $response->json('token'))
        ->getJson('/api/v1/me/profile')
        ->assertOk()
        ->assertJsonPath('public_id', $customer->public_id)
        ->assertJsonPath('phone_normalized', '96170456788');

    $this->withToken((string) $response->json('token'))->postJson('/api/v1/auth/customer/logout')->assertNoContent();
    $this->withToken((string) $response->json('token'))->getJson('/api/v1/me/profile')->assertUnauthorized();
});

it('requires an explicit tenant selector for root OTP requests when multiple tenants are active', function (): void {
    Tenant::create(['name' => 'Northline', 'slug' => 'northline', 'base_currency' => 'USD', 'collection_currency' => 'USD']);
    Tenant::create(['name' => 'Southline', 'slug' => 'southline', 'base_currency' => 'USD', 'collection_currency' => 'USD']);

    $this->postJson('/api/v1/auth/customer/otp/request', ['phone' => '+96170456789'])
        ->assertStatus(400);

    $this->withHeaders(['X-Tenant-Slug' => 'northline'])
        ->postJson('/api/v1/auth/customer/otp/request', ['phone' => '+96170456789'])
        ->assertOk();
});
