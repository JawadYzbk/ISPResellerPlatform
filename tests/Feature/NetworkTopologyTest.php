<?php

use App\Enums\ServiceStatus;
use App\Models\Customer;
use App\Models\DistributionBox;
use App\Models\NetworkBuilding;
use App\Models\Pop;
use App\Models\Service;
use App\Models\ServiceEvent;
use App\Models\Tenant;
use App\Models\User;
use App\Support\Tenancy;
use Database\Seeders\CapabilitySeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;

uses(RefreshDatabase::class);

function topologyOperator(Tenant $tenant): User
{
    $user = User::create([
        'tenant_id' => $tenant->id,
        'name' => 'Network operator',
        'email' => 'topology-'.$tenant->id.'@example.test',
        'password' => Hash::make('password'),
        'role' => 'tenant_owner',
    ]);
    app(CapabilitySeeder::class)->run();
    $user->assignRole('tenant_owner');
    $user->forceFill(['last_authenticated_at' => now()])->save();

    return $user;
}

it('creates tenant-safe buildings and boxes and assigns service ports', function (): void {
    $tenant = Tenant::create(['name' => 'Northline', 'slug' => 'northline', 'base_currency' => 'USD', 'collection_currency' => 'USD']);
    app(Tenancy::class)->set($tenant);
    $user = topologyOperator($tenant);
    $pop = Pop::create(['name' => 'Central POP', 'code' => 'POP-CENTRAL', 'status' => 'active']);
    $customer = Customer::factory()->create();
    $service = Service::factory()->for($customer)->create(['status' => ServiceStatus::Active]);

    $this->actingAs($user)
        ->post(route('operations.topology.buildings.store'), [
            'name' => 'Cedar Residence',
            'code' => 'CEDAR-01',
            'address' => '1 Cedar Street',
            'latitude' => '33.8938',
            'longitude' => '35.5018',
            'floors' => 8,
            'unit_count' => 64,
            'status' => 'active',
        ])
        ->assertSessionHasNoErrors();

    app(Tenancy::class)->set($tenant);
    $building = NetworkBuilding::query()->where('code', 'CEDAR-01')->firstOrFail();
    $this->actingAs($user)
        ->get(route('operations.topology.buildings.show', $building->public_id))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('Operations/TopologyBuildingShow')
            ->where('building.code', 'CEDAR-01')
            ->where('services.0.public_id', $service->public_id)
        );

    $this->actingAs($user)
        ->post(route('operations.topology.boxes.store', $building->public_id), [
            'pop_id' => $pop->id,
            'name' => 'Cedar Cabinet',
            'code' => 'CEDAR-CAB-01',
            'box_type' => 'cabinet',
            'capacity_ports' => 8,
            'latitude' => '33.8938',
            'longitude' => '35.5018',
            'status' => 'active',
        ])
        ->assertSessionHasNoErrors();

    app(Tenancy::class)->set($tenant);
    $box = DistributionBox::query()->where('code', 'CEDAR-CAB-01')->firstOrFail();
    $this->actingAs($user)
        ->post(route('operations.topology.services.assign', $service->public_id), [
            'distribution_box_id' => $box->public_id,
            'network_port' => 3,
        ])
        ->assertRedirect(route('operations.topology.buildings.show', $building->public_id));

    app(Tenancy::class)->set($tenant);
    expect($service->refresh()->network_building_id)->toBe($building->id)
        ->and($service->network_port)->toBe(3)
        ->and($box->refresh()->usedPorts())->toBe(1)
        ->and(ServiceEvent::query()->where('service_id', $service->id)->where('event_type', 'topology_assigned')->exists())->toBeTrue();

    $secondService = Service::factory()->for($customer)->create(['status' => ServiceStatus::Active]);
    $this->actingAs($user)
        ->post(route('operations.topology.services.assign', $secondService->public_id), [
            'distribution_box_id' => $box->public_id,
            'network_port' => 3,
        ])
        ->assertSessionHasErrors('topology');

    app(Tenancy::class)->set($tenant);
    $service->refresh();
    $this->actingAs($user)
        ->delete(route('operations.topology.services.unassign', $service->public_id))
        ->assertRedirect(route('operations.topology.buildings.show', $building->public_id));

    app(Tenancy::class)->set($tenant);
    expect($service->refresh()->network_building_id)->toBeNull()
        ->and($service->network_port)->toBeNull()
        ->and($box->refresh()->usedPorts())->toBe(0);
});

it('does not expose another tenant topology', function (): void {
    $tenant = Tenant::create(['name' => 'Northline', 'slug' => 'northline', 'base_currency' => 'USD', 'collection_currency' => 'USD']);
    $otherTenant = Tenant::create(['name' => 'Southline', 'slug' => 'southline', 'base_currency' => 'USD', 'collection_currency' => 'USD']);
    app(Tenancy::class)->set($otherTenant);
    $building = NetworkBuilding::create(['name' => 'South Tower', 'code' => 'SOUTH-01', 'status' => 'active']);
    app(Tenancy::class)->set($tenant);
    $user = topologyOperator($tenant);

    $this->actingAs($user)->get(route('operations.topology.buildings'))->assertOk()->assertInertia(fn ($page) => $page->where('buildings', []));
    $this->actingAs($user)->get(route('operations.topology.buildings.show', $building->public_id))->assertNotFound();
});
