<?php

use App\Models\PushToken;
use App\Models\Tenant;
use App\Models\User;
use App\Security\TwoFactorService;
use App\Support\Tenancy;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\RateLimiter;
use Laravel\Sanctum\PersonalAccessToken;
use OTPHP\TOTP;

uses(RefreshDatabase::class);

it('allows privileged users to reach the dashboard while web two-factor enforcement is disabled', function (): void {
    $tenant = Tenant::create(['name' => 'Northline', 'slug' => 'northline', 'base_currency' => 'USD', 'collection_currency' => 'USD']);
    User::create(['tenant_id' => $tenant->id, 'name' => 'Owner', 'email' => 'owner@example.test', 'password' => Hash::make('password'), 'role' => 'tenant_owner']);

    $this->post(route('login.store'), ['email' => 'owner@example.test', 'password' => 'password'])
        ->assertRedirect(route('dashboard'));

    $this->get(route('dashboard'))->assertOk();
});

it('issues and accepts a Sanctum token for a standard operator', function (): void {
    $tenant = Tenant::create(['name' => 'Southline', 'slug' => 'southline', 'base_currency' => 'USD', 'collection_currency' => 'USD']);
    User::create(['tenant_id' => $tenant->id, 'name' => 'Operator', 'email' => 'operator@example.test', 'password' => Hash::make('password'), 'role' => 'operator']);

    $response = $this->postJson('/api/v1/tokens', [
        'email' => 'operator@example.test',
        'password' => 'password',
        'device_name' => 'test-device',
    ])->assertOk()->assertJsonStructure(['token', 'type']);

    $this->withToken($response->json('token'))->getJson('/api/v1/me')
        ->assertOk()
        ->assertJsonPath('email', 'operator@example.test')
        ->assertJsonMissingPath('id')
        ->assertJsonMissingPath('tenant_id')
        ->assertJsonMissingPath('partner_id');
});

it('throttles repeated login attempts by account and IP', function (): void {
    RateLimiter::clear('account:unknown@example.test');
    RateLimiter::clear('ip:127.0.0.1');

    foreach (range(1, 5) as $attempt) {
        $this->post(route('login.store'), ['email' => 'unknown@example.test', 'password' => 'wrong'])->assertRedirect();
    }

    $this->post(route('login.store'), ['email' => 'unknown@example.test', 'password' => 'wrong'])->assertStatus(429);
});

it('supports the staff authentication contract with a separate two-factor exchange', function (): void {
    $tenant = Tenant::create(['name' => 'Northline', 'slug' => 'northline', 'base_currency' => 'USD', 'collection_currency' => 'USD']);
    $user = User::create(['tenant_id' => $tenant->id, 'name' => 'Owner', 'email' => 'staff-auth-owner@example.test', 'password' => Hash::make('password'), 'role' => 'tenant_owner']);
    $setup = app(TwoFactorService::class)->begin($user);
    $user->forceFill(['two_factor_confirmed_at' => now()])->save();

    $challenge = $this->postJson('/api/v1/auth/staff/login', [
        'email' => $user->email,
        'password' => 'password',
        'device_name' => 'operations-tablet',
        'device_id' => 'device-001',
    ])->assertStatus(422)
        ->assertJsonPath('two_factor_required', true)
        ->json('challenge_id');

    $token = $this->postJson('/api/v1/auth/staff/two-factor', [
        'challenge_id' => $challenge,
        'code' => TOTP::createFromSecret($setup['secret'])->now(),
    ])->assertOk()
        ->assertJsonPath('user.email', $user->email)
        ->json('token');

    $this->withToken($token)->getJson('/api/v1/auth/me')
        ->assertOk()
        ->assertJsonPath('email', $user->email)
        ->assertJsonPath('abilities.0', 'api');

    $this->withToken($token)->postJson('/api/v1/auth/logout')->assertNoContent();
    expect(PersonalAccessToken::findToken($token))->toBeNull();
});

it('registers encrypted push tokens and revokes every token for a device', function (): void {
    $tenant = Tenant::create(['name' => 'Southline', 'slug' => 'southline', 'base_currency' => 'USD', 'collection_currency' => 'USD']);
    $user = User::create(['tenant_id' => $tenant->id, 'name' => 'Operator', 'email' => 'device-auth-operator@example.test', 'password' => Hash::make('password'), 'role' => 'operator']);

    $token = $this->postJson('/api/v1/auth/staff/login', [
        'email' => $user->email,
        'password' => 'password',
        'device_name' => 'field-phone',
        'device_id' => 'device-001',
    ])->assertOk()->json('token');

    $this->withToken($token)->postJson('/api/v1/auth/push-token', [
        'token' => 'push-token-secret',
        'platform' => 'android',
        'app' => 'isp-manager',
    ])->assertNoContent();

    app(Tenancy::class)->set($tenant);
    expect(PushToken::query()->where('user_id', $user->id)->count())->toBe(1)
        ->and(PushToken::query()->firstOrFail()->token_encrypted)->toBe('push-token-secret');

    $this->withToken($token)->postJson('/api/v1/auth/devices/device-001/revoke')->assertNoContent();
    expect(PersonalAccessToken::query()->where('tokenable_id', $user->id)->where('device_id', 'device-001')->count())->toBe(0);
});
