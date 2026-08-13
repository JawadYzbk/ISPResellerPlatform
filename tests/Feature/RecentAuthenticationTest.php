<?php

use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Route;

uses(RefreshDatabase::class);

it('redirects stale sessions before a sensitive action', function (): void {
    $tenant = Tenant::create(['name' => 'Northline', 'slug' => 'northline', 'base_currency' => 'USD', 'collection_currency' => 'USD']);
    $user = User::create(['tenant_id' => $tenant->id, 'name' => 'Operator', 'email' => 'operator@example.test', 'password' => Hash::make('password'), 'role' => 'operator']);
    $user->forceFill(['last_authenticated_at' => now()->subSeconds((int) config('auth.password_timeout') + 60)])->save();

    $this->actingAs($user)->post(route('two-factor.setup.confirm'), ['code' => '123456'])
        ->assertRedirect(route('security.reauthenticate'));
});

it('refreshes the authentication timestamp after a correct password', function (): void {
    $tenant = Tenant::create(['name' => 'Southline', 'slug' => 'southline', 'base_currency' => 'USD', 'collection_currency' => 'USD']);
    $user = User::create(['tenant_id' => $tenant->id, 'name' => 'Operator', 'email' => 'operator@example.test', 'password' => Hash::make('password'), 'role' => 'operator']);
    $user->forceFill(['last_authenticated_at' => now()->subSeconds((int) config('auth.password_timeout') + 60)])->save();

    $this->actingAs($user)
        ->withHeader('referer', route('settings.general'))
        ->post(route('two-factor.setup.confirm'), ['code' => '123456'])
        ->assertRedirect(route('security.reauthenticate'));

    $this->actingAs($user)->post(route('security.reauthenticate.store'), ['password' => 'password'])
        ->assertRedirect(route('settings.general'));

    expect($user->refresh()->last_authenticated_at)->toBeInstanceOf(Carbon::class)
        ->and($user->last_authenticated_at->greaterThan(now()->subMinute()))->toBeTrue();
});

it('returns to the referring workspace page when reauthentication is opened directly', function (): void {
    $tenant = Tenant::create(['name' => 'Eastline', 'slug' => 'eastline', 'base_currency' => 'USD', 'collection_currency' => 'USD']);
    $user = User::create(['tenant_id' => $tenant->id, 'name' => 'Operator', 'email' => 'operator-direct-reauth@example.test', 'password' => Hash::make('password'), 'role' => 'operator']);
    $user->forceFill(['last_authenticated_at' => now()->subSeconds((int) config('auth.password_timeout') + 60)])->save();

    $this->actingAs($user)
        ->withHeader('referer', route('settings.general'))
        ->get(route('security.reauthenticate'))
        ->assertOk();

    $this->actingAs($user)->post(route('security.reauthenticate.store'), ['password' => 'password'])
        ->assertRedirect(route('settings.general'));
});

it('keeps a recent authentication valid throughout the configured timeout', function (): void {
    Route::middleware(['web', 'recent-auth'])->get('/test-sensitive-action', fn () => response('ok'));

    $tenant = Tenant::create(['name' => 'Westline', 'slug' => 'westline', 'base_currency' => 'USD', 'collection_currency' => 'USD']);
    $user = User::create(['tenant_id' => $tenant->id, 'name' => 'Operator', 'email' => 'operator-window@example.test', 'password' => Hash::make('password'), 'role' => 'operator']);
    $user->forceFill(['last_authenticated_at' => now()->subMinutes(11)])->save();

    $this->actingAs($user)->get('/test-sensitive-action')->assertOk()->assertSee('ok');
});
