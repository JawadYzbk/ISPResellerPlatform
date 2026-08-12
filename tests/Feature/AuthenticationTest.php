<?php

use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;

uses(RefreshDatabase::class);

it('allows a tenant user to sign in and reach the dashboard', function (): void {
    $tenant = Tenant::create(['name' => 'Northline', 'slug' => 'northline', 'base_currency' => 'USD', 'collection_currency' => 'USD']);
    User::create(['tenant_id' => $tenant->id, 'name' => 'Maya Haddad', 'email' => 'maya@example.test', 'password' => Hash::make('password'), 'role' => 'operator']);

    $this->post(route('login.store'), ['email' => 'maya@example.test', 'password' => 'password'])
        ->assertRedirect(route('dashboard'))
        ->assertSessionHas('success_title', 'Welcome back')
        ->assertSessionHas('success', 'You are signed in and ready to work.');

    $this->get(route('dashboard'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('Dashboard/Index')
            ->loadDeferredProps(['dashboard-metrics', 'dashboard-attention'], fn ($deferred) => $deferred->hasAll(['metrics', 'attentionQueue']))
        );
});
