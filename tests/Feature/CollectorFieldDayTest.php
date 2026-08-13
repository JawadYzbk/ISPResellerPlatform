<?php

use App\Models\CollectorFieldDay;
use App\Models\Tenant;
use App\Models\User;
use App\Support\Tenancy;
use Database\Seeders\CapabilitySeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;

uses(RefreshDatabase::class);

/** @return array{Tenant, User, User} */
function fieldDayWorkspace(): array
{
    $tenant = Tenant::factory()->create(['name' => 'Northline', 'slug' => 'northline']);
    app(Tenancy::class)->set($tenant);
    $manager = User::create([
        'tenant_id' => $tenant->id,
        'name' => 'Operations Manager',
        'email' => 'field-day-manager@example.test',
        'password' => Hash::make('password'),
        'role' => 'operations_manager',
    ]);
    $collector = User::create([
        'tenant_id' => $tenant->id,
        'name' => 'Nadia Collector',
        'email' => 'field-day-collector@example.test',
        'password' => Hash::make('password'),
        'role' => 'collector',
    ]);
    app(CapabilitySeeder::class)->run();
    app(Tenancy::class)->set($tenant);
    $manager->assignRole('operations_manager');
    $collector->assignRole('collector');

    return [$tenant, $manager, $collector];
}

it('records one active collector field day with bounded location evidence', function (): void {
    [$tenant, , $collector] = fieldDayWorkspace();

    $this->actingAs($collector)
        ->postJson(route('field.check-in'), [
            'latitude' => 33.8938,
            'longitude' => 35.5018,
            'accuracy_meters' => 18,
        ])
        ->assertCreated()
        ->assertJsonPath('message', 'Field day started.')
        ->assertJsonPath('data.status', 'active')
        ->assertJsonPath('data.check_in.accuracy_meters', 18);

    $this->actingAs($collector)
        ->postJson(route('field.check-in'), [
            'latitude' => 33.8938,
            'longitude' => 35.5018,
            'accuracy_meters' => 20,
        ])
        ->assertUnprocessable()
        ->assertJsonPath('message', 'Your field day is already active.');

    app(Tenancy::class)->set($tenant);
    expect(CollectorFieldDay::query()->count())->toBe(1);
});

it('ends the active field day and requires a new location capture', function (): void {
    [$tenant, , $collector] = fieldDayWorkspace();
    $this->actingAs($collector)->postJson(route('field.check-in'), [
        'latitude' => 33.8938,
        'longitude' => 35.5018,
        'accuracy_meters' => 18,
    ])->assertCreated();

    $this->actingAs($collector)
        ->postJson(route('field.check-out'), [
            'latitude' => 33.8990,
            'longitude' => 35.5100,
            'accuracy_meters' => 25,
        ])
        ->assertOk()
        ->assertJsonPath('message', 'Field day ended.')
        ->assertJsonPath('data.status', 'completed')
        ->assertJsonPath('data.check_out.accuracy_meters', 25);

    app(Tenancy::class)->set($tenant);
    $fieldDay = CollectorFieldDay::query()->firstOrFail();
    expect($fieldDay->checked_out_at)->not->toBeNull()
        ->and($fieldDay->check_out_source)->toBe('web_geolocation');

    $this->actingAs($collector)
        ->postJson(route('field.check-out'), [
            'latitude' => 33.8990,
            'longitude' => 35.5100,
        ])
        ->assertUnprocessable()
        ->assertJsonPath('message', 'No active field day is available to end.');
});

it('validates coordinates and limits field attendance to collector accounts', function (): void {
    [, $manager, $collector] = fieldDayWorkspace();

    $this->actingAs($collector)
        ->postJson(route('field.check-in'), [
            'latitude' => 100,
            'longitude' => 35.5018,
            'accuracy_meters' => 6000,
        ])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['latitude', 'accuracy_meters']);

    $this->actingAs($manager)
        ->postJson(route('field.check-in'), [
            'latitude' => 33.8938,
            'longitude' => 35.5018,
        ])
        ->assertForbidden();
});

it('shows field attendance to operations managers but not collectors', function (): void {
    [$tenant, $manager, $collector] = fieldDayWorkspace();
    $this->actingAs($collector)->postJson(route('field.check-in'), [
        'latitude' => 33.8938,
        'longitude' => 35.5018,
        'accuracy_meters' => 18,
    ])->assertCreated();

    $this->actingAs($manager)
        ->get(route('operations.collector-check-ins'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('Operations/CollectorCheckIns')
            ->where('fieldDays.0.collector.name', $collector->name)
            ->where('fieldDays.0.status', 'active')
            ->where('fieldDays.0.check_in.latitude', 33.8938));

    app(Tenancy::class)->set($tenant);
    $this->actingAs($collector)->get(route('operations.collector-check-ins'))->assertForbidden();
});
