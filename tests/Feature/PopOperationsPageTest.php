<?php

use App\Models\Pop;
use App\Models\Router;
use App\Models\Tenant;
use App\Models\UpstreamLink;
use App\Models\User;
use App\Support\Tenancy;
use Database\Seeders\CapabilitySeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;

uses(RefreshDatabase::class);

it('renders tenant-scoped POP inventory and upstream link detail', function (): void {
    $tenant = Tenant::create(['name' => 'Northline', 'slug' => 'northline', 'base_currency' => 'USD', 'collection_currency' => 'USD']);
    $user = User::create(['tenant_id' => $tenant->id, 'name' => 'Network operator', 'email' => 'pops@example.test', 'password' => Hash::make('password'), 'role' => 'tenant_owner']);
    app(CapabilitySeeder::class)->run();
    app(Tenancy::class)->set($tenant);
    $user->assignRole('tenant_owner');
    $pop = Pop::create(['name' => 'Central POP', 'code' => 'CENTRAL', 'address' => 'Main street', 'status' => 'active']);
    Router::create(['pop_id' => $pop->id, 'name' => 'Core router', 'host' => 'core.example.test', 'username' => 'api', 'password_encrypted' => 'secret']);
    UpstreamLink::create(['pop_id' => $pop->id, 'provider_name' => 'Transit provider', 'capacity_mbps' => 1000, 'monthly_cost_amount' => 125000, 'currency' => 'USD', 'contract_start' => '2026-01-01', 'notes' => 'Primary transit']);

    $this->actingAs($user)->get(route('operations.pops'))->assertOk()->assertInertia(fn ($page) => $page
        ->component('Operations/Pops')
        ->where('pops.data.0.name', 'Central POP')
        ->where('pops.data.0.routers_count', 1)
        ->where('pops.data.0.upstream_links_count', 1)
    );

    app(Tenancy::class)->set($tenant);
    $this->actingAs($user)->get(route('operations.pops.show', $pop->id))->assertOk()->assertInertia(fn ($page) => $page
        ->component('Operations/PopShow')
        ->where('pop.routers.0.name', 'Core router')
        ->where('pop.upstream_links.0.monthly_cost_amount', 125000)
    );
});

it('does not expose another tenant POP', function (): void {
    $tenant = Tenant::create(['name' => 'Northline', 'slug' => 'northline', 'base_currency' => 'USD', 'collection_currency' => 'USD']);
    $otherTenant = Tenant::create(['name' => 'Southline', 'slug' => 'southline', 'base_currency' => 'USD', 'collection_currency' => 'USD']);
    $user = User::create(['tenant_id' => $tenant->id, 'name' => 'Network operator', 'email' => 'pops-isolation@example.test', 'password' => Hash::make('password'), 'role' => 'tenant_owner']);
    app(CapabilitySeeder::class)->run();
    app(Tenancy::class)->set($otherTenant);
    $pop = Pop::create(['name' => 'South POP', 'code' => 'SOUTH', 'status' => 'active']);
    app(Tenancy::class)->set($tenant);
    $user->assignRole('tenant_owner');

    $this->actingAs($user)->get(route('operations.pops'))->assertOk()->assertInertia(fn ($page) => $page->where('pops.total', 0));
    $this->actingAs($user)->get(route('operations.pops.show', $pop->id))->assertNotFound();
});

it('allows network operators to create and update POPs and record upstream links', function (): void {
    $tenant = Tenant::create(['name' => 'Northline', 'slug' => 'northline', 'base_currency' => 'USD', 'collection_currency' => 'USD']);
    $user = User::create(['tenant_id' => $tenant->id, 'name' => 'Network operator', 'email' => 'pops-write@example.test', 'password' => Hash::make('password'), 'role' => 'tenant_owner']);
    app(CapabilitySeeder::class)->run();
    app(Tenancy::class)->set($tenant);
    $user->assignRole('tenant_owner');
    $user->forceFill(['last_authenticated_at' => now()])->save();

    $this->actingAs($user)
        ->post(route('operations.pops.store'), ['name' => 'Central POP', 'code' => 'central', 'address' => 'Main street', 'status' => 'active'])
        ->assertRedirect();

    app(Tenancy::class)->set($tenant);
    $pop = Pop::query()->where('code', 'CENTRAL')->firstOrFail();

    $this->actingAs($user)
        ->put(route('operations.pops.update', $pop), ['name' => 'Central Core', 'code' => 'CENTRAL', 'address' => 'Updated street', 'status' => 'maintenance'])
        ->assertRedirect(route('operations.pops.show', $pop));

    $this->actingAs($user)
        ->post(route('operations.pops.upstream-links.store', $pop), [
            'provider_name' => 'Transit provider',
            'capacity_mbps' => 1000,
            'monthly_cost_amount' => 125000,
            'currency' => 'usd',
            'contract_start' => '2026-01-01',
            'contract_end' => '2026-12-31',
            'notes' => 'Primary transit',
        ])
        ->assertRedirect(route('operations.pops.show', $pop));

    app(Tenancy::class)->set($tenant);
    expect($pop->refresh()->name)->toBe('Central Core')
        ->and($pop->status)->toBe('maintenance')
        ->and($pop->upstreamLinks()->firstOrFail()->currency)->toBe('USD');
});

it('forbids POP writes without network provisioning capability', function (): void {
    $tenant = Tenant::create(['name' => 'Northline', 'slug' => 'northline', 'base_currency' => 'USD', 'collection_currency' => 'USD']);
    $user = User::create(['tenant_id' => $tenant->id, 'name' => 'Support agent', 'email' => 'pops-support@example.test', 'password' => Hash::make('password'), 'role' => 'support_agent']);
    app(CapabilitySeeder::class)->run();
    app(Tenancy::class)->set($tenant);
    $user->assignRole('support_agent');
    $user->forceFill(['last_authenticated_at' => now()])->save();

    $this->actingAs($user)
        ->post(route('operations.pops.store'), ['name' => 'Blocked POP', 'code' => 'BLOCKED', 'status' => 'active'])
        ->assertForbidden();
});
