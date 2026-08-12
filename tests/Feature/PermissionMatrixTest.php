<?php

use App\Models\Tenant;
use App\Models\User;
use App\Support\Tenancy;
use Database\Seeders\CapabilitySeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('allows a seeded tenant owner to load every static staff workspace', function (): void {
    $tenant = Tenant::create([
        'name' => 'Northline',
        'slug' => 'northline',
        'base_currency' => 'USD',
        'collection_currency' => 'USD',
    ]);
    $owner = User::create([
        'tenant_id' => $tenant->id,
        'name' => 'Owner',
        'email' => 'permission-matrix-owner@example.test',
        'password' => 'password',
        'role' => 'tenant_owner',
    ]);

    app(CapabilitySeeder::class)->run();
    app(Tenancy::class)->set($tenant);
    $owner->assignRole('tenant_owner');

    $paths = [
        '/dashboard',
        '/search',
        '/settings/general',
        '/settings/users',
        '/customers',
        '/services',
        '/operations/network-commands',
        '/operations/sessions',
        '/operations/incidents',
        '/operations/routers',
        '/operations/routers/create',
        '/operations/pops',
        '/operations/ip-pools',
        '/billing/invoices',
        '/billing/payments',
        '/billing/shifts',
        '/billing/exchange-rates',
        '/operations/tickets',
        '/operations/work-orders',
        '/operations/work-orders/calendar',
        '/operations/inventory',
        '/operations/imports',
        '/operations/credentials',
        '/operations/suppliers',
        '/plans',
        '/partners/commercial',
        '/reports/finance',
        '/reports/operations',
    ];

    foreach ($paths as $path) {
        $this->actingAs($owner)->get($path)->assertOk();
    }
});

it('denies representative workspaces when the role lacks their capability', function (): void {
    $tenant = Tenant::create([
        'name' => 'Northline',
        'slug' => 'northline',
        'base_currency' => 'USD',
        'collection_currency' => 'USD',
    ]);
    $cashier = User::create([
        'tenant_id' => $tenant->id,
        'name' => 'Cashier',
        'email' => 'permission-matrix-cashier@example.test',
        'password' => 'password',
        'role' => 'cashier',
    ]);

    app(CapabilitySeeder::class)->run();
    app(Tenancy::class)->set($tenant);
    $cashier->assignRole('cashier');

    $this->actingAs($cashier)->get('/billing/invoices')->assertOk();
    $this->actingAs($cashier)->get('/settings/general')->assertForbidden();
    $this->actingAs($cashier)->get('/operations/routers')->assertForbidden();
    $this->actingAs($cashier)->get('/partners/commercial')->assertForbidden();
    $this->actingAs($cashier)->get('/reports/operations')->assertForbidden();
});
