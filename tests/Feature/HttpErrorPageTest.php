<?php

use App\Models\Tenant;
use App\Models\User;
use App\Support\Tenancy;
use Database\Seeders\CapabilitySeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;

uses(RefreshDatabase::class);

it('renders a useful Inertia page for a missing route', function (): void {
    $this->get('/does-not-exist')
        ->assertNotFound()
        ->assertInertia(fn ($page) => $page
            ->component('Errors/Http')
            ->where('status', 404)
            ->where('title', 'We could not find that page.')
        );
});

it('renders a useful Inertia page when a staff capability is missing', function (): void {
    $tenant = Tenant::create(['name' => 'Northline', 'slug' => 'northline', 'base_currency' => 'USD', 'collection_currency' => 'USD']);
    $user = User::create([
        'tenant_id' => $tenant->id,
        'name' => 'Cashier',
        'email' => 'errors-cashier@example.test',
        'password' => Hash::make('password'),
        'role' => 'cashier',
    ]);

    app(CapabilitySeeder::class)->run();
    app(Tenancy::class)->set($tenant);
    $user->assignRole('cashier');

    $this->actingAs($user)
        ->get(route('settings.general'))
        ->assertForbidden()
        ->assertInertia(fn ($page) => $page
            ->component('Errors/Http')
            ->where('status', 403)
            ->where('title', 'This action is unauthorized.')
        );
});
