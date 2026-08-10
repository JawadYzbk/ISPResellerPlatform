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
