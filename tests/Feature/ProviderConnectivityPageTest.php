<?php

use App\Models\Tenant;
use App\Models\User;
use App\Support\Tenancy;
use Database\Seeders\CapabilitySeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;

uses(RefreshDatabase::class);

it('lets a settings manager run provider checks from pilot readiness', function (): void {
    config()->set([
        'services.frankfurter.enabled' => false,
        'services.payments.driver' => 'null',
        'services.whish.enabled' => false,
        'services.whatsapp.mode' => 'cloud',
    ]);
    $tenant = Tenant::create([
        'name' => 'Northline',
        'slug' => 'northline',
        'base_currency' => 'USD',
        'collection_currency' => 'LBP',
    ]);
    $user = User::create([
        'tenant_id' => $tenant->id,
        'name' => 'Owner',
        'email' => 'provider-check-owner@example.test',
        'password' => Hash::make('password'),
        'role' => 'tenant_owner',
    ]);
    app(CapabilitySeeder::class)->run();
    app(Tenancy::class)->set($tenant);
    $user->assignRole('tenant_owner');
    $user->forceFill(['last_authenticated_at' => now()])->save();

    $this->actingAs($user)
        ->post(route('settings.readiness.provider-check'))
        ->assertRedirect(route('settings.readiness'))
        ->assertSessionHas('provider_checks.frankfurter.status', 'disabled')
        ->assertSessionHas('success_title', 'Provider checks');

    $this->actingAs($user)
        ->get(route('settings.readiness'))
        ->assertInertia(fn ($page) => $page
            ->component('Settings/Readiness')
            ->where('providerChecks.frankfurter.status', 'disabled')
            ->where('providerChecks.stripe.status', 'disabled')
            ->where('providerChecks.whish.status', 'disabled')
            ->where('providerChecks.whatsapp_web.status', 'disabled')
        );
});
