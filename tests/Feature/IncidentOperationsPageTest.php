<?php

use App\Enums\IncidentStatus;
use App\Models\Incident;
use App\Models\Service;
use App\Models\Tenant;
use App\Models\User;
use App\Support\Tenancy;
use Database\Seeders\CapabilitySeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;

uses(RefreshDatabase::class);

it('renders filtered incidents and a tenant-safe detail view', function (): void {
    $tenant = Tenant::create(['name' => 'Northline', 'slug' => 'northline', 'base_currency' => 'USD', 'collection_currency' => 'USD']);
    $user = User::create(['tenant_id' => $tenant->id, 'name' => 'Network admin', 'email' => 'incidents@example.test', 'password' => Hash::make('password'), 'role' => 'tenant_owner']);
    app(CapabilitySeeder::class)->run();
    app(Tenancy::class)->set($tenant);
    $user->assignRole('tenant_owner');
    $service = Service::factory()->create();
    $incident = Incident::create([
        'service_id' => $service->id,
        'type' => 'service_drift',
        'severity' => 'warning',
        'status' => IncidentStatus::Open,
        'title' => 'Service drift detected',
        'description' => 'Device state differs from the commercial state.',
        'opened_at' => now()->subMinutes(10),
        'metadata' => ['source' => 'reconciliation'],
    ]);

    $this->actingAs($user)
        ->get(route('operations.incidents', ['severity' => 'warning', 'search' => 'drift']))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('Operations/Incidents')
            ->where('incidents.total', 1)
            ->where('incidents.data.0.public_id', $incident->public_id)
            ->where('incidents.data.0.service.public_id', $service->public_id)
            ->where('filters.severity', 'warning'));

    $this->actingAs($user)
        ->get(route('operations.incidents.show', $incident->public_id))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('Operations/IncidentShow')
            ->where('incident.description', 'Device state differs from the commercial state.')
            ->where('incident.metadata.source', 'reconciliation'));
});

it('does not expose another tenant incident', function (): void {
    $tenant = Tenant::create(['name' => 'Northline', 'slug' => 'northline', 'base_currency' => 'USD', 'collection_currency' => 'USD']);
    $otherTenant = Tenant::create(['name' => 'Southline', 'slug' => 'southline', 'base_currency' => 'USD', 'collection_currency' => 'USD']);
    $user = User::create(['tenant_id' => $tenant->id, 'name' => 'Network admin', 'email' => 'incident-isolation@example.test', 'password' => Hash::make('password'), 'role' => 'tenant_owner']);
    app(CapabilitySeeder::class)->run();
    app(Tenancy::class)->set($otherTenant);
    $service = Service::factory()->create();
    $incident = Incident::create(['service_id' => $service->id, 'type' => 'service_drift', 'severity' => 'warning', 'status' => IncidentStatus::Open, 'title' => 'Hidden incident', 'opened_at' => now()]);
    app(Tenancy::class)->set($tenant);
    $user->assignRole('tenant_owner');

    $this->actingAs($user)
        ->get(route('operations.incidents'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page->where('incidents.total', 0));

    $this->actingAs($user)->get(route('operations.incidents.show', $incident->public_id))->assertNotFound();
});
