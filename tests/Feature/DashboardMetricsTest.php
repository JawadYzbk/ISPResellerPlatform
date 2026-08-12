<?php

use App\Actions\CreateInvoice;
use App\Actions\GetDashboardMetrics;
use App\Actions\IssueInvoice;
use App\Actions\RecordPayment;
use App\Enums\IncidentStatus;
use App\Enums\NetworkState;
use App\Enums\ServiceStatus;
use App\Models\CurrentSession;
use App\Models\Incident;
use App\Models\NetworkCommand;
use App\Models\Plan;
use App\Models\Router;
use App\Models\Service;
use App\Models\Tenant;
use App\Models\User;
use App\Support\Tenancy;
use Database\Seeders\CapabilitySeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;

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

it('includes finance and service trend metrics for authorised dashboard users', function (): void {
    $tenant = Tenant::create(['name' => 'Northline', 'slug' => 'northline', 'base_currency' => 'USD', 'collection_currency' => 'USD']);
    app(Tenancy::class)->set($tenant);
    $user = User::create(['tenant_id' => $tenant->id, 'name' => 'Owner', 'email' => 'owner-dashboard@example.test', 'password' => Hash::make('password'), 'role' => 'tenant_owner']);
    app(CapabilitySeeder::class)->run();
    $user->givePermissionTo('reports.finance');

    $service = Service::factory()->create(['status' => ServiceStatus::Active]);
    $plan = Plan::query()->findOrFail($service->plan_id);
    $plan->prices()->create(['currency' => 'USD', 'amount_minor' => 3500, 'effective_from' => now()->subDay()]);
    $invoice = app(IssueInvoice::class)->handle(app(CreateInvoice::class)->handle($service->customer, $plan, $service));
    app(RecordPayment::class)->handle($service->customer, 1000, 'USD', 'cash', 'dashboard-metrics-001', $invoice);

    $metrics = app(GetDashboardMetrics::class)->handle($user);

    expect($metrics['owner']['baseCurrency'])->toBe('USD')
        ->and($metrics['owner']['revenue'])->toBe(3500)
        ->and($metrics['owner']['collected'])->toBe(1000)
        ->and($metrics['owner']['collectionRate'])->toBe(28.57)
        ->and($metrics['owner']['margin'])->toBe(3500)
        ->and($metrics['owner']['currencyMetrics']['USD']['collected'])->toBe(1000)
        ->and($metrics['owner']['statusTrend'])->toHaveCount(6);

    $restricted = User::create(['tenant_id' => $tenant->id, 'name' => 'Collector', 'email' => 'collector-dashboard@example.test', 'password' => Hash::make('password'), 'role' => 'collector']);

    expect(app(GetDashboardMetrics::class)->handle($restricted)['owner'])->toBeNull();
});
