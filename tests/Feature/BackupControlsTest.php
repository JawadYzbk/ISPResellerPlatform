<?php

use App\Models\Tenant;
use App\Models\User;
use App\Support\Tenancy;
use Database\Seeders\CapabilitySeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Hash;

uses(RefreshDatabase::class);

it('lets a settings manager create a backup from readiness', function (): void {
    $tenant = Tenant::create([
        'name' => 'Northline',
        'slug' => 'northline',
        'base_currency' => 'USD',
        'collection_currency' => 'USD',
    ]);
    $user = User::create([
        'tenant_id' => $tenant->id,
        'name' => 'Owner',
        'email' => 'backup-owner@example.test',
        'password' => Hash::make('password'),
        'role' => 'tenant_owner',
    ]);
    app(CapabilitySeeder::class)->run();
    app(Tenancy::class)->set($tenant);
    $user->assignRole('tenant_owner');
    $user->forceFill(['last_authenticated_at' => now()])->save();
    Artisan::shouldReceive('call')
        ->once()
        ->with('backup:run', [
            '--disable-notifications' => true,
            '--isolated' => true,
            '--quiet' => true,
        ])
        ->andReturn(0);

    $this->actingAs($user)
        ->post(route('settings.readiness.backup'))
        ->assertRedirect()
        ->assertSessionHas('success_title', 'Backup created')
        ->assertSessionHas('success', 'A verified application backup was created successfully.');
});

it('does not expose backup creation to users without settings capability', function (): void {
    config()->set('security.enforce_web_two_factor', false);
    $tenant = Tenant::create([
        'name' => 'Northline',
        'slug' => 'northline',
        'base_currency' => 'USD',
        'collection_currency' => 'USD',
    ]);
    $user = User::create([
        'tenant_id' => $tenant->id,
        'name' => 'Cashier',
        'email' => 'backup-cashier@example.test',
        'password' => Hash::make('password'),
        'role' => 'cashier',
    ]);
    app(CapabilitySeeder::class)->run();
    app(Tenancy::class)->set($tenant);
    $user->assignRole('cashier');
    $user->forceFill(['last_authenticated_at' => now()])->save();

    $this->actingAs($user)
        ->post(route('settings.readiness.backup'))
        ->assertForbidden();
});
