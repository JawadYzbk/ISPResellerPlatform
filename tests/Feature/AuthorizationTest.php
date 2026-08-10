<?php

use App\Authorization\PermissionCatalog;
use App\Models\Tenant;
use App\Models\User;
use App\Support\Tenancy;
use Database\Seeders\CapabilitySeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('keeps role permissions inside their tenant team', function (): void {
    $north = Tenant::create(['name' => 'North', 'slug' => 'north', 'base_currency' => 'USD', 'collection_currency' => 'USD']);
    $south = Tenant::create(['name' => 'South', 'slug' => 'south', 'base_currency' => 'USD', 'collection_currency' => 'USD']);
    $user = User::create(['tenant_id' => $north->id, 'name' => 'Cashier', 'email' => 'cashier@example.test', 'password' => 'password']);

    app(CapabilitySeeder::class)->run();
    app(Tenancy::class)->set($north);
    $user->assignRole('cashier');

    expect($user->can('payments.collect'))->toBeTrue();

    app(Tenancy::class)->set($south);
    $user->refresh();

    expect($user->can('payments.collect'))->toBeFalse();
});

it('rejects capabilities that are not in the catalog', function (): void {
    expect(fn (): mixed => PermissionCatalog::assertKnown('billing.make_up_a_permission'))
        ->toThrow(InvalidArgumentException::class);
});

it('reconciles a legacy admin account with the full tenant role', function (): void {
    $tenant = Tenant::create(['name' => 'North', 'slug' => 'north', 'base_currency' => 'USD', 'collection_currency' => 'USD']);
    $admin = User::create(['tenant_id' => $tenant->id, 'name' => 'Legacy Admin', 'email' => 'legacy-admin@example.test', 'password' => 'password', 'role' => 'admin']);

    app(CapabilitySeeder::class)->run();
    app(Tenancy::class)->set($tenant);

    expect($admin->refresh()->hasRole('admin'))->toBeTrue()
        ->and($admin->can('customers.view'))->toBeTrue()
        ->and($admin->can('billing.invoices.view'))->toBeTrue()
        ->and($admin->can('network.disconnect'))->toBeTrue()
        ->and($admin->requiresTwoFactor())->toBeTrue();
});

it('reconciles the role column for existing tenant owners', function (): void {
    $tenant = Tenant::create(['name' => 'North', 'slug' => 'north', 'base_currency' => 'USD', 'collection_currency' => 'USD']);
    $owner = User::create(['tenant_id' => $tenant->id, 'name' => 'Owner', 'email' => 'tenant-owner@example.test', 'password' => 'password', 'role' => 'tenant_owner']);

    app(CapabilitySeeder::class)->run();
    app(Tenancy::class)->set($tenant);

    expect($owner->refresh()->hasRole('tenant_owner'))->toBeTrue()
        ->and($owner->can('customers.view'))->toBeTrue()
        ->and($owner->can('settings.manage'))->toBeTrue()
        ->and($owner->can('partners.manage'))->toBeTrue();
});

it('allows a seeded tenant owner to load representative operator pages', function (): void {
    $tenant = Tenant::create(['name' => 'North', 'slug' => 'north', 'base_currency' => 'USD', 'collection_currency' => 'USD']);
    $owner = User::create(['tenant_id' => $tenant->id, 'name' => 'Owner', 'email' => 'owner-pages@example.test', 'password' => 'password', 'role' => 'tenant_owner']);

    app(CapabilitySeeder::class)->run();
    app(Tenancy::class)->set($tenant);
    $owner->assignRole('tenant_owner');

    $this->actingAs($owner)->get('/customers')->assertOk();
    $this->actingAs($owner)->get('/billing/invoices')->assertOk();
    $this->actingAs($owner)->get('/partners/commercial')->assertOk();
});
