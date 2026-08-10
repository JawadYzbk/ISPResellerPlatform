<?php

use App\Models\Pop;
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

    app(Tenancy::class)->set($tenant);
    $this->actingAs($user)
        ->get(route('operations.routers.show', $router->public_id))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('Operations/RouterShow')
            ->where('router.name', 'Core')
            ->where('router.services_count', 1)
            ->missing('router.password')
            ->where('canEdit', true)
        );

    $user->forceFill(['last_authenticated_at' => now()])->save();
    app(Tenancy::class)->set($tenant);
    $this->actingAs($user)
        ->put(route('operations.routers.update', $router->public_id), [
            'name' => 'Core updated',
            'host' => 'router-updated.example.test',
            'api_port' => 443,
            'username' => 'api',
            'password' => '',
            'radius_secret' => '',
            'coa_port' => 1700,
            'tls_verify' => false,
            'pop_id' => null,
        ])
        ->assertRedirect(route('operations.routers.show', $router->public_id));
    app(Tenancy::class)->set($tenant);
    expect($router->refresh()->name)->toBe('Core updated')
        ->and($router->password_encrypted)->toBe('secret');

    Http::fake(['https://router-updated.example.test/rest/system/resource' => Http::response(['version' => '7.15.2', 'board-name' => 'CHR'])]);
    app(Tenancy::class)->set($tenant);
    $this->actingAs($user)
        ->post(route('operations.routers.health', $router->public_id))
        ->assertRedirect(route('operations.routers'))
        ->assertSessionHas('success', 'Router Core updated is reachable.');

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
    $this->actingAs($user)->get(route('operations.routers.show', $router->public_id))->assertNotFound();
    $this->actingAs($user)->post(route('operations.routers.health', $router->public_id))->assertNotFound();
});

it('registers a router through the capability-gated web form without echoing secrets', function (): void {
    $tenant = Tenant::create(['name' => 'Northline', 'slug' => 'northline', 'base_currency' => 'USD', 'collection_currency' => 'USD']);
    $user = User::create(['tenant_id' => $tenant->id, 'name' => 'Network admin', 'email' => 'router-create@example.test', 'password' => Hash::make('password'), 'role' => 'tenant_owner']);
    app(CapabilitySeeder::class)->run();
    app(Tenancy::class)->set($tenant);
    $user->assignRole('tenant_owner');
    $pop = Pop::create(['name' => 'Central POP', 'code' => 'POP-CENTRAL', 'status' => 'active']);
    $user->forceFill(['last_authenticated_at' => now()])->save();

    $this->actingAs($user)
        ->get(route('operations.routers.create'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('Operations/RouterCreate')
            ->where('pops.0.id', $pop->id)
            ->missing('pops.0.password')
        );

    app(Tenancy::class)->set($tenant);
    $response = $this->actingAs($user)
        ->post(route('operations.routers.store'), [
            'name' => 'Central MikroTik',
            'host' => 'router.example.test',
            'api_port' => 443,
            'username' => 'api-user',
            'password' => 'long-router-password',
            'radius_secret' => 'long-radius-secret',
            'coa_port' => 1700,
            'tls_verify' => true,
            'pop_id' => $pop->id,
        ]);
    $response->assertRedirect(route('operations.routers'));

    app(Tenancy::class)->set($tenant);
    $router = Router::query()->where('host', 'router.example.test')->firstOrFail();
    expect($router->password_encrypted)->toBe('long-router-password')
        ->and($router->radius_secret_encrypted)->toBe('long-radius-secret');
});
