<?php

use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\RateLimiter;

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
