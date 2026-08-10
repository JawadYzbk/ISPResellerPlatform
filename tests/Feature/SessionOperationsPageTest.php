<?php

use App\Models\CurrentSession;
use App\Models\Service;
use App\Models\Tenant;
use App\Models\User;
use App\Support\Tenancy;
use Database\Seeders\CapabilitySeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;

uses(RefreshDatabase::class);

it('renders searchable active sessions for the current tenant', function (): void {
    $tenant = Tenant::create(['name' => 'Northline', 'slug' => 'northline', 'base_currency' => 'USD', 'collection_currency' => 'USD']);
    $user = User::create(['tenant_id' => $tenant->id, 'name' => 'Network admin', 'email' => 'sessions@example.test', 'password' => Hash::make('password'), 'role' => 'tenant_owner']);
    app(CapabilitySeeder::class)->run();
    app(Tenancy::class)->set($tenant);
    $user->assignRole('tenant_owner');
    $service = Service::factory()->create();
    $customer = $service->customer()->firstOrFail();
    CurrentSession::create([
        'service_id' => $service->id,
        'username' => $service->username,
        'acct_session_id' => 'sessions-page-001',
        'nasname' => 'core-router-01',
        'framed_ip' => '10.0.0.25',
        'acct_start_time' => now()->subHour(),
        'last_seen_at' => now(),
        'input_octets' => 1000,
        'output_octets' => 2000,
    ]);
    CurrentSession::create([
        'service_id' => $service->id,
        'username' => $service->username,
        'acct_session_id' => 'sessions-page-stopped',
        'nasname' => 'core-router-01',
        'last_seen_at' => now()->subMinutes(2),
        'stopped_at' => now()->subMinute(),
    ]);

    $this->actingAs($user)
        ->get(route('operations.sessions', ['search' => '10.0.0.25']))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('Operations/Sessions')
            ->where('sessions.total', 1)
            ->where('sessions.data.0.session_id', 'sessions-page-001')
            ->where('sessions.data.0.service.public_id', $service->public_id)
            ->where('sessions.data.0.customer.public_id', $customer->public_id)
            ->where('filters.search', '10.0.0.25')
            ->where('canDisconnect', true));
});

it('does not expose another tenant active sessions', function (): void {
    $tenant = Tenant::create(['name' => 'Northline', 'slug' => 'northline', 'base_currency' => 'USD', 'collection_currency' => 'USD']);
    $otherTenant = Tenant::create(['name' => 'Southline', 'slug' => 'southline', 'base_currency' => 'USD', 'collection_currency' => 'USD']);
    $user = User::create(['tenant_id' => $tenant->id, 'name' => 'Network admin', 'email' => 'sessions-isolation@example.test', 'password' => Hash::make('password'), 'role' => 'tenant_owner']);
    app(CapabilitySeeder::class)->run();
    app(Tenancy::class)->set($otherTenant);
    $service = Service::factory()->create();
    CurrentSession::create(['service_id' => $service->id, 'username' => $service->username, 'acct_session_id' => 'hidden-session-001', 'nasname' => 'south-router', 'last_seen_at' => now()]);
    app(Tenancy::class)->set($tenant);
    $user->assignRole('tenant_owner');

    $this->actingAs($user)
        ->get(route('operations.sessions'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page->where('sessions.total', 0));
});
