<?php

use App\Actions\RequestPortalOtp;
use App\Actions\VerifyPortalOtp;
use App\Models\Customer;
use App\Models\Tenant;
use App\Support\Tenancy;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('returns the same OTP request shape for known and unknown phones', function (): void {
    $tenant = Tenant::create(['name' => 'Northline', 'slug' => 'northline', 'base_currency' => 'USD', 'collection_currency' => 'USD']);
    app(Tenancy::class)->set($tenant);
    Customer::factory()->create(['phone' => '+96170456789']);

    $known = $this->postJson('/api/v1/portal/northline/otp/request', ['phone' => '+961 70 456 789']);
    $unknown = $this->postJson('/api/v1/portal/northline/otp/request', ['phone' => '+961 70 999 999']);

    $known->assertOk()->assertJsonStructure(['challenge_id', 'expires_at']);
    $unknown->assertOk()->assertJsonStructure(['challenge_id', 'expires_at']);
});

it('hashes the OTP and issues a separate portal session after verification', function (): void {
    $tenant = Tenant::create(['name' => 'Southline', 'slug' => 'southline', 'base_currency' => 'USD', 'collection_currency' => 'USD']);
    app(Tenancy::class)->set($tenant);
    Customer::factory()->create(['phone' => '+96170456789']);
    $result = app(RequestPortalOtp::class)->handle($tenant, '+961 70 456 789');
    $session = app(VerifyPortalOtp::class)->handle($tenant, $result['challenge']->public_id, $result['code']);

    expect($session['token'])->toStartWith('portal_')
        ->and($result['challenge']->refresh()->code_hash)->not->toBe($result['code'])
        ->and($this->withToken($session['token'])->getJson('/api/v1/portal/southline/me')->assertOk()->json('phone_normalized'))->toBe('96170456789');

    $this->withToken($session['token'])->postJson('/api/v1/portal/southline/logout')->assertNoContent();
    $this->withToken($session['token'])->getJson('/api/v1/portal/southline/me')->assertUnauthorized();
});
