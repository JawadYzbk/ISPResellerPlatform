<?php

use App\Models\Tenant;
use App\Models\User;
use App\Support\Tenancy;
use Database\Seeders\CapabilitySeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;

uses(RefreshDatabase::class);

it('allows a tenant user to sign in at their selected default view', function (): void {
    $tenant = Tenant::create(['name' => 'Northline', 'slug' => 'northline', 'base_currency' => 'USD', 'collection_currency' => 'USD']);
    $user = User::create(['tenant_id' => $tenant->id, 'name' => 'Maya Haddad', 'email' => 'maya@example.test', 'password' => Hash::make('password'), 'role' => 'tenant_owner', 'default_view' => '/customers']);
    app(CapabilitySeeder::class)->run();
    app(Tenancy::class)->set($tenant);
    $user->assignRole('tenant_owner');

    $this->withSession(['url.intended' => route('dashboard')])
        ->post(route('login.store'), ['email' => 'maya@example.test', 'password' => 'password'])
        ->assertRedirect(url('/customers'))
        ->assertSessionHas('success_title', 'Welcome back')
        ->assertSessionHas('success', 'You are signed in and ready to work.');

    $this->get(route('dashboard'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('Dashboard/Index')
            ->loadDeferredProps(['dashboard-metrics', 'dashboard-attention'], fn ($deferred) => $deferred->hasAll(['metrics', 'attentionQueue']))
        );

    $this->get('/')->assertRedirect(url('/customers'));
});
