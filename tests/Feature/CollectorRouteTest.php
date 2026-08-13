<?php

use App\Models\CollectorFieldDay;
use App\Models\CollectorRoute;
use App\Models\CollectorRouteStop;
use App\Models\Customer;
use App\Models\Tenant;
use App\Models\User;
use App\Models\Zone;
use App\Support\Tenancy;
use Database\Seeders\CapabilitySeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;

uses(RefreshDatabase::class);

/** @return array{Tenant, User, User, User, Zone, Zone} */
function collectorRouteWorkspace(): array
{
    $tenant = Tenant::factory()->create(['name' => 'Northline', 'slug' => 'northline']);
    app(Tenancy::class)->set($tenant);
    $manager = User::create([
        'tenant_id' => $tenant->id,
        'name' => 'Operations Manager',
        'email' => 'route-manager@example.test',
        'password' => Hash::make('password'),
        'role' => 'operations_manager',
    ]);
    $collector = User::create([
        'tenant_id' => $tenant->id,
        'name' => 'Nadia Collector',
        'email' => 'route-collector@example.test',
        'password' => Hash::make('password'),
        'role' => 'collector',
        'collector_all_zones' => false,
    ]);
    $otherCollector = User::create([
        'tenant_id' => $tenant->id,
        'name' => 'Other Collector',
        'email' => 'route-other@example.test',
        'password' => Hash::make('password'),
        'role' => 'collector',
    ]);
    app(CapabilitySeeder::class)->run();
    app(Tenancy::class)->set($tenant);
    $manager->assignRole('operations_manager');
    $collector->assignRole('collector');
    $otherCollector->assignRole('collector');
    $manager->forceFill(['last_authenticated_at' => now()])->save();
    $north = Zone::factory()->create(['name' => 'North', 'code' => 'NORTH']);
    $south = Zone::factory()->create(['name' => 'South', 'code' => 'SOUTH']);
    $collector->activeCollectorZoneAssignments()->create([
        'tenant_id' => $tenant->id,
        'zone_id' => $north->id,
        'assigned_by_id' => $manager->id,
        'started_at' => now(),
    ]);

    return [$tenant, $manager, $collector, $otherCollector, $north, $south];
}

it('plans an ordered route using only customers inside the collector territory', function (): void {
    [$tenant, $manager, $collector, , $north, $south] = collectorRouteWorkspace();
    $first = Customer::factory()->create(['zone_id' => $north->id, 'first_name' => 'First']);
    $second = Customer::factory()->create(['zone_id' => $north->id, 'first_name' => 'Second']);
    $outside = Customer::factory()->create(['zone_id' => $south->id, 'first_name' => 'Outside']);
    $date = now()->toDateString();

    $this->actingAs($manager)
        ->get(route('operations.collector-routes', ['date' => $date]))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('Operations/CollectorRoutes')
            ->where('collectors.0.name', $collector->name)
            ->where('collectors.0.customer_ids', [$first->id, $second->id])
            ->has('customers', 3));

    $this->actingAs($manager)
        ->post(route('operations.collector-routes.store'), [
            'collector_id' => $collector->id,
            'route_date' => $date,
            'customer_ids' => [$second->id, $first->id],
        ])
        ->assertRedirect(route('operations.collector-routes', ['date' => $date]))
        ->assertSessionHas('success', "{$collector->name}'s route was planned.");

    app(Tenancy::class)->set($tenant);
    $route = CollectorRoute::query()->firstOrFail();
    expect($route->status)->toBe('planned')
        ->and($route->stops()->orderBy('position')->pluck('customer_id')->all())->toBe([$second->id, $first->id]);

    $this->actingAs($manager)
        ->post(route('operations.collector-routes.store'), [
            'collector_id' => $collector->id,
            'route_date' => $date,
            'customer_ids' => [$outside->id],
        ])
        ->assertSessionHasErrors(['customer_ids']);

    app(Tenancy::class)->set($tenant);
    expect($route->refresh()->stops()->count())->toBe(2);
});

it('exposes today route to the assigned collector field desk', function (): void {
    [$tenant, $manager, $collector, , $north] = collectorRouteWorkspace();
    $customer = Customer::factory()->create([
        'zone_id' => $north->id,
        'first_name' => 'Route',
        'latitude' => 33.8938,
        'longitude' => 35.5018,
    ]);
    $this->actingAs($manager)->post(route('operations.collector-routes.store'), [
        'collector_id' => $collector->id,
        'route_date' => now()->toDateString(),
        'customer_ids' => [$customer->id],
    ])->assertRedirect();

    app(Tenancy::class)->set($tenant);
    $this->actingAs($collector)
        ->get(route('field.index'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('Field/Index')
            ->where('route.status', 'planned')
            ->where('route.stops.0.customer.id', $customer->public_id)
            ->where('route.stops.0.customer.latitude', 33.8938));
});

it('records location-backed visit outcomes and completes the route', function (): void {
    [$tenant, $manager, $collector, , $north] = collectorRouteWorkspace();
    $first = Customer::factory()->create(['zone_id' => $north->id]);
    $second = Customer::factory()->create(['zone_id' => $north->id]);
    $this->actingAs($manager)->post(route('operations.collector-routes.store'), [
        'collector_id' => $collector->id,
        'route_date' => now()->toDateString(),
        'customer_ids' => [$first->id, $second->id],
    ])->assertRedirect();
    app(Tenancy::class)->set($tenant);
    $route = CollectorRoute::query()->firstOrFail();
    $stops = $route->stops()->orderBy('position')->get();

    $this->actingAs($collector)
        ->postJson(route('field.route-stops.record', $stops[0]), [
            'outcome' => 'no_answer',
            'latitude' => 33.8938,
            'longitude' => 35.5018,
            'accuracy_meters' => 15,
        ])
        ->assertUnprocessable()
        ->assertJsonPath('message', 'Start your field day before recording visit outcomes.');

    app(Tenancy::class)->set($tenant);
    CollectorFieldDay::create([
        'user_id' => $collector->id,
        'checked_in_at' => now(),
        'check_in_latitude' => 33.89,
        'check_in_longitude' => 35.50,
    ]);

    $this->actingAs($collector)
        ->postJson(route('field.route-stops.record', $stops[0]), [
            'outcome' => 'no_answer',
            'note' => 'Called twice',
            'latitude' => 33.8938,
            'longitude' => 35.5018,
            'accuracy_meters' => 15,
        ])
        ->assertOk()
        ->assertJsonPath('data.status', 'in_progress')
        ->assertJsonPath('data.completed_count', 1)
        ->assertJsonPath('data.stops.0.note', 'Called twice');

    $this->actingAs($collector)
        ->postJson(route('field.route-stops.record', $stops[1]), [
            'outcome' => 'collected',
            'latitude' => 33.8940,
            'longitude' => 35.5020,
            'accuracy_meters' => 12,
        ])
        ->assertOk()
        ->assertJsonPath('data.status', 'completed')
        ->assertJsonPath('data.completed_count', 2);

    app(Tenancy::class)->set($tenant);
    expect($route->refresh()->completed_at)->not->toBeNull()
        ->and(CollectorRouteStop::query()->where('outcome', 'pending')->count())->toBe(0);
});

it('does not expose another collectors stop and does not overwrite a recorded outcome', function (): void {
    [$tenant, $manager, $collector, $otherCollector, $north] = collectorRouteWorkspace();
    $customer = Customer::factory()->create(['zone_id' => $north->id]);
    $this->actingAs($manager)->post(route('operations.collector-routes.store'), [
        'collector_id' => $collector->id,
        'route_date' => now()->toDateString(),
        'customer_ids' => [$customer->id],
    ])->assertRedirect();
    app(Tenancy::class)->set($tenant);
    $stop = CollectorRouteStop::query()->firstOrFail();
    CollectorFieldDay::create([
        'user_id' => $collector->id,
        'checked_in_at' => now(),
        'check_in_latitude' => 33.89,
        'check_in_longitude' => 35.50,
    ]);

    $this->actingAs($otherCollector)
        ->postJson(route('field.route-stops.record', $stop), [
            'outcome' => 'refused',
            'latitude' => 33.89,
            'longitude' => 35.50,
        ])
        ->assertNotFound();

    $payload = ['outcome' => 'refused', 'latitude' => 33.89, 'longitude' => 35.50];
    $this->actingAs($collector)->postJson(route('field.route-stops.record', $stop), $payload)->assertOk();
    $this->actingAs($collector)
        ->postJson(route('field.route-stops.record', $stop), $payload)
        ->assertUnprocessable()
        ->assertJsonPath('message', 'This visit outcome has already been recorded.');
});

it('rejects visit outcomes on routes from another day', function (): void {
    [$tenant, $manager, $collector, , $north] = collectorRouteWorkspace();
    $customer = Customer::factory()->create(['zone_id' => $north->id]);
    $this->actingAs($manager)->post(route('operations.collector-routes.store'), [
        'collector_id' => $collector->id,
        'route_date' => now($tenant->timezone)->subDay()->toDateString(),
        'customer_ids' => [$customer->id],
    ])->assertRedirect();
    app(Tenancy::class)->set($tenant);
    $stop = CollectorRouteStop::query()->firstOrFail();
    CollectorFieldDay::create([
        'user_id' => $collector->id,
        'checked_in_at' => now(),
        'check_in_latitude' => 33.89,
        'check_in_longitude' => 35.50,
    ]);

    $this->actingAs($collector)
        ->postJson(route('field.route-stops.record', $stop), [
            'outcome' => 'no_answer',
            'latitude' => 33.89,
            'longitude' => 35.50,
        ])
        ->assertUnprocessable()
        ->assertJsonPath('message', 'Visit outcomes can only be recorded on today\'s route.');

    app(Tenancy::class)->set($tenant);
    expect($stop->refresh()->outcome)->toBe('pending');
});
