<?php

use App\Models\Tenant;
use App\Models\User;
use App\Support\Tenancy;
use Database\Seeders\CapabilitySeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Route;

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

it('keeps shared props available when a stale form token expires', function (): void {
    Route::post('/test-expired', fn () => abort(419));

    $response = $this->withHeaders([
        'X-Inertia' => 'true',
        'X-Requested-With' => 'XMLHttpRequest',
        'Accept' => 'text/html, application/xhtml+xml',
    ])->post('/test-expired', [
        '_token' => 'stale-token',
    ]);

    $page = json_decode($response->getContent(), true);

    expect($response->status())->toBe(419)
        ->and($page['component'])->toBe('Errors/Http')
        ->and($page['props']['status'])->toBe(419)
        ->and($page['props']['app']['locale'])->toBe('en')
        ->and($page['props']['auth']['user'])->toBeNull();
});
