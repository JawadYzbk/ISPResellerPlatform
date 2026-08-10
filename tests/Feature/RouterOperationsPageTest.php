<?php

use App\Models\Router;
use App\Models\Service;
use App\Models\Tenant;
use App\Models\User;
use App\Support\Tenancy;
use Database\Seeders\CapabilitySeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Http;

uses(RefreshDatabase::class);

it('renders tenant-safe router operations and checks device health', function (): void {
    $tenant = Tenant::create(['name' => 'Northline', 'slug' => 'northline', 'base_currency' => 'USD', 'collection_currency' => 'USD']);
    $user = User::create(['tenant_id' => $tenant->id, 'name' => 'Network admin', 'email' => 'network@example.test', 'password' => Hash::make('password'), 'role' => 'tenant_owner']);
    app(CapabilitySeeder::class)->run();
    app(Tenancy::class)->set($tenant);
    $user->assignRole('tenant_owner');
    $router = Router::create(['name' => 'Core', 'host' => 'router.example.test', 'username' => 'api', 'password_encrypted' => 'secret', 'status' => 'unknown']);
    $service = Service::factory()->create(['router_id' => $router->id]);

    $this->actingAs($user)
        ->get(route('operations.routers', ['status' => 'unknown']))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('Operations/Routers')
            ->where('routers.data.0.public_id', $router->public_id)
            ->where('routers.data.0.host', 'router.example.test')
            ->where('routers.data.0.services_count', 1)
            ->where('filters.status', 'unknown')
            ->where('canCheckHealth', true)
        );

    Http::fake(['https://router.example.test/rest/system/resource' => Http::response(['version' => '7.15.2', 'board-name' => 'CHR'])]);
    app(Tenancy::class)->set($tenant);
    $this->actingAs($user)
        ->post(route('operations.routers.health', $router->public_id))
        ->assertRedirect(route('operations.routers'))
        ->assertSessionHas('success', 'Router Core is reachable.');

    app(Tenancy::class)->set($tenant);
    expect($router->refresh()->status)->toBe('online')->and($router->last_seen_at)->not->toBeNull();
});

it('does not expose routers from another tenant', function (): void {
    $tenant = Tenant::create(['name' => 'Northline', 'slug' => 'northline', 'base_currency' => 'USD', 'collection_currency' => 'USD']);
    $otherTenant = Tenant::create(['name' => 'Southline', 'slug' => 'southline', 'base_currency' => 'USD', 'collection_currency' => 'USD']);
    $user = User::create(['tenant_id' => $tenant->id, 'name' => 'Network admin', 'email' => 'network@example.test', 'password' => Hash::make('password'), 'role' => 'tenant_owner']);
    app(CapabilitySeeder::class)->run();
    app(Tenancy::class)->set($otherTenant);
    $router = Router::create(['name' => 'South core', 'host' => 'south.example.test', 'username' => 'api', 'password_encrypted' => 'secret']);
    app(Tenancy::class)->set($tenant);
    $user->assignRole('tenant_owner');

    $this->actingAs($user)->get(route('operations.routers'))->assertOk()->assertInertia(fn ($page) => $page->where('routers.total', 0));
    $this->actingAs($user)->post(route('operations.routers.health', $router->public_id))->assertNotFound();
});
