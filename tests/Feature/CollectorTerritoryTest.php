<?php

use App\Models\CollectorZoneAssignment;
use App\Models\Customer;
use App\Models\Tenant;
use App\Models\User;
use App\Models\Zone;
use App\Support\Tenancy;
use Database\Seeders\CapabilitySeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;

uses(RefreshDatabase::class);

/** @return array{Tenant, User, User} */
function collectorTerritoryWorkspace(): array
{
    $tenant = Tenant::factory()->create(['name' => 'Northline', 'slug' => 'northline']);
    app(Tenancy::class)->set($tenant);
    $owner = User::create([
        'tenant_id' => $tenant->id,
        'name' => 'Owner',
        'email' => 'territory-owner@example.test',
        'password' => Hash::make('password'),
        'role' => 'tenant_owner',
    ]);
    $collector = User::create([
        'tenant_id' => $tenant->id,
        'name' => 'Nadia Collector',
        'email' => 'territory-collector@example.test',
        'password' => Hash::make('password'),
        'role' => 'collector',
    ]);
    app(CapabilitySeeder::class)->run();
    app(Tenancy::class)->set($tenant);
    $owner->assignRole('tenant_owner');
    $collector->assignRole('collector');
    $owner->forceFill(['last_authenticated_at' => now()])->save();

    return [$tenant, $owner, $collector];
}

it('lets workspace managers restrict a collector to zones and preserves assignment history', function (): void {
    [$tenant, $owner, $collector] = collectorTerritoryWorkspace();
    $root = Zone::factory()->create(['name' => 'Beirut', 'code' => 'BEIRUT']);
    $child = Zone::factory()->create(['name' => 'Achrafieh', 'code' => 'ACHRAFIEH', 'parent_id' => $root->id]);
    $other = Zone::factory()->create(['name' => 'Tripoli', 'code' => 'TRIPOLI']);

    $this->actingAs($owner)
        ->get(route('settings.collector-territories'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('Settings/CollectorTerritories')
            ->where('collectors.0.email', $collector->email)
            ->where('collectors.0.all_zones', true)
            ->has('zones', 4));

    $this->actingAs($owner)
        ->patch(route('settings.collector-territories.update', $collector), [
            'all_zones' => false,
            'zone_ids' => [$root->id],
        ])
        ->assertRedirect(route('settings.collector-territories'))
        ->assertSessionHas('success', "{$collector->name}'s territory was updated.");

    app(Tenancy::class)->set($tenant);
    expect($collector->refresh()->collector_all_zones)->toBeFalse()
        ->and(CollectorZoneAssignment::query()->where('user_id', $collector->id)->whereNull('ended_at')->pluck('zone_id')->all())
        ->toBe([$root->id]);

    $this->actingAs($owner)
        ->patch(route('settings.collector-territories.update', $collector), [
            'all_zones' => false,
            'zone_ids' => [$other->id],
        ])
        ->assertRedirect(route('settings.collector-territories'));

    app(Tenancy::class)->set($tenant);
    expect(CollectorZoneAssignment::query()->where('user_id', $collector->id)->count())->toBe(2)
        ->and(CollectorZoneAssignment::query()->where('user_id', $collector->id)->whereNotNull('ended_at')->value('zone_id'))->toBe($root->id)
        ->and(CollectorZoneAssignment::query()->where('user_id', $collector->id)->whereNull('ended_at')->value('zone_id'))->toBe($other->id)
        ->and($child->parent_id)->toBe($root->id);
});

it('enforces collector territories for sync, customer queues, descendants and detail access', function (): void {
    [$tenant, $owner, $collector] = collectorTerritoryWorkspace();
    $root = Zone::factory()->create(['name' => 'Beirut', 'code' => 'BEIRUT']);
    $child = Zone::factory()->create(['name' => 'Achrafieh', 'code' => 'ACHRAFIEH', 'parent_id' => $root->id]);
    $other = Zone::factory()->create(['name' => 'Tripoli', 'code' => 'TRIPOLI']);
    $rootCustomer = Customer::factory()->create(['zone_id' => $root->id, 'first_name' => 'Root']);
    $childCustomer = Customer::factory()->create(['zone_id' => $child->id, 'first_name' => 'Child']);
    $otherCustomer = Customer::factory()->create(['zone_id' => $other->id, 'first_name' => 'Other']);

    $this->actingAs($owner)->patch(route('settings.collector-territories.update', $collector), [
        'all_zones' => false,
        'zone_ids' => [$root->id],
    ])->assertRedirect(route('settings.collector-territories'));

    app(Tenancy::class)->set($tenant);
    $collector->refresh();
    $sync = $this->actingAs($collector)->getJson(route('field.sync'))->assertOk();
    expect(collect($sync->json('data.customers'))->pluck('id')->all())
        ->toBe([$rootCustomer->public_id, $childCustomer->public_id])
        ->and($sync->json('territory.mode'))->toBe('assigned')
        ->and($sync->json('territory.zone_ids'))->toBe([$root->id, $child->id]);

    $token = $collector->createToken('territory-device', ['api', 'staff:collector'])->plainTextToken;
    $this->withToken($token)
        ->getJson('/api/v1/collector/customers')
        ->assertOk()
        ->assertJsonCount(2, 'data');

    $this->withToken($token)
        ->getJson('/api/v1/collector/customers/'.$childCustomer->public_id)
        ->assertOk()
        ->assertJsonPath('data.id', $childCustomer->public_id);

    $this->withToken($token)
        ->getJson('/api/v1/collector/customers/'.$otherCustomer->public_id)
        ->assertNotFound();
});

it('rejects empty restricted territories and zones from another tenant', function (): void {
    [$tenant, $owner, $collector] = collectorTerritoryWorkspace();
    $otherTenant = Tenant::factory()->create(['name' => 'Otherline', 'slug' => 'otherline']);
    app(Tenancy::class)->set($otherTenant);
    $foreignZone = Zone::factory()->create(['name' => 'Foreign', 'code' => 'FOREIGN']);
    app(Tenancy::class)->set($tenant);

    $this->actingAs($owner)
        ->patch(route('settings.collector-territories.update', $collector), [
            'all_zones' => false,
            'zone_ids' => [],
        ])
        ->assertSessionHasErrors(['zone_ids']);

    $this->actingAs($owner)
        ->patch(route('settings.collector-territories.update', $collector), [
            'all_zones' => false,
            'zone_ids' => [$foreignZone->id],
        ])
        ->assertSessionHasErrors(['zone_ids.0']);

    expect($collector->refresh()->collector_all_zones)->toBeTrue();
});

it('does not expose collector territory settings to collectors', function (): void {
    [, , $collector] = collectorTerritoryWorkspace();

    $this->actingAs($collector)->get(route('settings.collector-territories'))->assertForbidden();
});
