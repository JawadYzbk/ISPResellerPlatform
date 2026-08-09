<?php

use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Hash;

uses(RefreshDatabase::class);

it('redirects stale sessions before a sensitive action', function (): void {
    $tenant = Tenant::create(['name' => 'Northline', 'slug' => 'northline', 'base_currency' => 'USD', 'collection_currency' => 'USD']);
    $user = User::create(['tenant_id' => $tenant->id, 'name' => 'Operator', 'email' => 'operator@example.test', 'password' => Hash::make('password'), 'role' => 'operator', 'last_authenticated_at' => now()->subMinutes(11)]);

    $this->actingAs($user)->post(route('two-factor.setup.confirm'), ['code' => '123456'])
        ->assertRedirect(route('security.reauthenticate'));
});

it('refreshes the authentication timestamp after a correct password', function (): void {
    $tenant = Tenant::create(['name' => 'Southline', 'slug' => 'southline', 'base_currency' => 'USD', 'collection_currency' => 'USD']);
    $user = User::create(['tenant_id' => $tenant->id, 'name' => 'Operator', 'email' => 'operator@example.test', 'password' => Hash::make('password'), 'role' => 'operator', 'last_authenticated_at' => now()->subMinutes(11)]);

    $this->actingAs($user)->post(route('security.reauthenticate.store'), ['password' => 'password'])
        ->assertRedirect(route('dashboard'));

    expect($user->refresh()->last_authenticated_at)->toBeInstanceOf(Carbon::class)
        ->and($user->last_authenticated_at->greaterThan(now()->subMinute()))->toBeTrue();
});
