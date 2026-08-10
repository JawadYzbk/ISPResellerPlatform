<?php

use App\Models\Tenant;
use App\Models\User;
use App\Support\Tenancy;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;

uses(RefreshDatabase::class);

it('issues role-derived API abilities and allows a narrower requested scope', function (): void {
    $tenant = Tenant::factory()->create(['name' => 'Northline', 'slug' => 'northline']);
    app(Tenancy::class)->set($tenant);
    $user = User::create(['tenant_id' => $tenant->id, 'name' => 'Collector', 'email' => 'token-collector@example.test', 'password' => Hash::make('password'), 'role' => 'collector']);

    $response = $this->postJson('/api/v1/tokens', [
        'email' => $user->email,
        'password' => 'password',
        'device_name' => 'collector-phone',
        'abilities' => ['staff:collector'],
    ]);

    $response->assertOk()->assertJsonPath('abilities.0', 'api')->assertJsonPath('abilities.1', 'staff:collector');
    expect($user->refresh()->last_authenticated_at)->toBeInstanceOf(Carbon::class);
});

it('blocks a collector token from technician routes', function (): void {
    $tenant = Tenant::factory()->create(['name' => 'Northline', 'slug' => 'northline']);
    app(Tenancy::class)->set($tenant);
    $user = User::create(['tenant_id' => $tenant->id, 'name' => 'Collector', 'email' => 'token-collector-route@example.test', 'password' => Hash::make('password'), 'role' => 'collector']);
    $token = $user->createToken('collector-phone', ['api', 'staff:collector'])->plainTextToken;

    $this->withToken($token)->getJson('/api/v1/technician/work-orders')->assertForbidden();
});

it('rejects an API token scope outside the user role', function (): void {
    $tenant = Tenant::factory()->create(['name' => 'Northline', 'slug' => 'northline']);
    app(Tenancy::class)->set($tenant);
    $user = User::create(['tenant_id' => $tenant->id, 'name' => 'Collector', 'email' => 'token-collector-denied@example.test', 'password' => Hash::make('password'), 'role' => 'collector']);

    $this->postJson('/api/v1/tokens', [
        'email' => $user->email,
        'password' => 'password',
        'device_name' => 'collector-phone',
        'abilities' => ['staff:operator'],
    ])->assertForbidden();
});
