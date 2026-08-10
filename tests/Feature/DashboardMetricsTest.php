<?php

use App\Actions\GetDashboardMetrics;
use App\Enums\IncidentStatus;
use App\Enums\NetworkState;
use App\Models\CurrentSession;
use App\Models\Incident;
use App\Models\NetworkCommand;
use App\Models\Router;
use App\Models\Service;
use App\Models\Tenant;
use App\Support\Tenancy;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('reports current NOC signals in tenant scope', function (): void {
    $tenant = Tenant::create(['name' => 'Northline', 'slug' => 'northline', 'base_currency' => 'USD', 'collection_currency' => 'USD']);
    app(Tenancy::class)->set($tenant);
    Router::create(['name' => 'Core-01', 'host' => '192.0.2.10', 'username' => 'api', 'password_encrypted' => 'secret', 'status' => 'offline']);
    $service = Service::factory()->create(['network_state' => NetworkState::Drifted]);
    CurrentSession::create(['service_id' => $service->id, 'username' => $service->username, 'acct_session_id' => 'noc-session-001', 'nasname' => 'Core-01', 'last_seen_at' => now()]);
    NetworkCommand::create(['service_id' => $service->id, 'action' => 'activate', 'status' => 'failed', 'desired_state_version' => 1, 'last_error' => 'router unreachable']);
    Incident::create(['router_id' => null, 'service_id' => $service->id, 'type' => 'service_drift', 'severity' => 'warning', 'status' => IncidentStatus::Open, 'title' => 'Service drift', 'opened_at' => now()]);

    expect(app(GetDashboardMetrics::class)->handle())
        ->toMatchArray(['offlineRouters' => 1, 'activeSessions' => 1, 'driftedServices' => 1, 'failedCommands' => 1, 'openIncidents' => 1]);
});
