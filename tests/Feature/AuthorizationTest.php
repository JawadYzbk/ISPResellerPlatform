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
