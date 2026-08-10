<?php

use App\Models\IpAddress;
use App\Models\IpPool;
use App\Models\Tenant;
use App\Models\User;
use App\Support\Tenancy;
use Database\Seeders\CapabilitySeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;

uses(RefreshDatabase::class);

function ipPoolOperationsUser(Tenant $tenant, string $role = 'tenant_owner'): User
{
    $user = User::create([
        'tenant_id' => $tenant->id,
        'name' => 'Network operator',
        'email' => "network-{$tenant->id}-{$role}@example.test",
        'password' => Hash::make('password'),
        'role' => $role,
    ]);

    app(CapabilitySeeder::class)->run();
    app(Tenancy::class)->set($tenant);
    $user->assignRole($role);

    return $user;
}

it('renders IP pools and records addresses for network operators', function (): void {
    $tenant = Tenant::create(['name' => 'Northline', 'slug' => 'northline', 'base_currency' => 'USD', 'collection_currency' => 'USD']);
    $user = ipPoolOperationsUser($tenant);
    $user->forceFill(['last_authenticated_at' => now()])->save();

    $this->actingAs($user)
        ->get(route('operations.ip-pools'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('Operations/IpPools')
            ->where('pools', [])
            ->where('canManage', true)
        );

    $this->actingAs($user)
        ->post(route('operations.ip-pools.store'), [
            'name' => 'Subscriber IPv4',
            'cidr' => '10.20.10.0/24',
            'gateway' => '10.20.10.1',
            'type' => 'dynamic',
            'version' => 4,
            'is_active' => true,
        ])
        ->assertRedirect();

    app(Tenancy::class)->set($tenant);
    $pool = IpPool::query()->where('name', 'Subscriber IPv4')->firstOrFail();

    $this->actingAs($user)
        ->post(route('operations.ip-pools.addresses.store', $pool), [
            'address' => '10.20.10.10',
            'status' => 'free',
        ])
        ->assertRedirect(route('operations.ip-pools', ['pool_id' => $pool->id]));

    app(Tenancy::class)->set($tenant);
    expect(IpAddress::query()->where('ip_pool_id', $pool->id)->firstOrFail()->status)->toBe('free');
});

it('keeps IP pools isolated between tenants', function (): void {
    $tenant = Tenant::create(['name' => 'Northline', 'slug' => 'northline', 'base_currency' => 'USD', 'collection_currency' => 'USD']);
    $otherTenant = Tenant::create(['name' => 'Southline', 'slug' => 'southline', 'base_currency' => 'USD', 'collection_currency' => 'USD']);
    $user = ipPoolOperationsUser($tenant);
    $otherUser = ipPoolOperationsUser($otherTenant);

    app(Tenancy::class)->set($tenant);
    $pool = IpPool::create(['name' => 'Northline IPv4', 'cidr' => '10.30.0.0/24', 'version' => 4, 'type' => 'dynamic', 'is_active' => true]);

    $this->actingAs($otherUser)
        ->get(route('operations.ip-pools', ['pool_id' => $pool->id]))
        ->assertNotFound();

    $this->actingAs($otherUser)
        ->post(route('operations.ip-pools.addresses.store', $pool), ['address' => '10.30.0.10', 'status' => 'free'])
        ->assertNotFound();
});

it('shows the page to viewers but forbids IP pool changes without provisioning capability', function (): void {
    $tenant = Tenant::create(['name' => 'Northline', 'slug' => 'northline', 'base_currency' => 'USD', 'collection_currency' => 'USD']);
    $user = ipPoolOperationsUser($tenant, 'support_agent');
    $user->forceFill(['last_authenticated_at' => now()])->save();

    $this->actingAs($user)
        ->get(route('operations.ip-pools'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page->where('canManage', false));

    $this->actingAs($user)
        ->post(route('operations.ip-pools.store'), [
            'name' => 'Blocked',
            'cidr' => '10.40.0.0/24',
            'type' => 'blocked',
            'version' => 4,
        ])
        ->assertForbidden();
});
