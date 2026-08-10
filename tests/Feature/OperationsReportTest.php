<?php

use App\Actions\GetOperationsReport;
use App\Enums\NetworkState;
use App\Enums\ServiceStatus;
use App\Models\Service;
use App\Models\Tenant;
use App\Support\Tenancy;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('summarizes live service and network operations by status', function (): void {
    $tenant = Tenant::factory()->create(['name' => 'Northline', 'slug' => 'northline']);
    app(Tenancy::class)->set($tenant);
    Service::factory()->create(['status' => ServiceStatus::Active, 'network_state' => NetworkState::InSync, 'expires_at' => now()->addDays(3)]);
    Service::factory()->create(['status' => ServiceStatus::Suspended, 'network_state' => NetworkState::Drifted, 'expires_at' => now()->addDays(20)]);
    Service::factory()->create(['status' => ServiceStatus::Pending, 'network_state' => NetworkState::Failed, 'expires_at' => now()->addDays(2)]);

    $report = app(GetOperationsReport::class)->handle();

    expect($report['service_counts_by_status'])->toMatchArray(['active' => 1, 'pending' => 1, 'suspended' => 1])
        ->and($report['expiring_services'])->toBe(2)
        ->and($report['network_drift'])->toBe(2)
        ->and($report['active_sessions'])->toBe(0)
        ->and($report['offline_routers'])->toBe(0)
        ->and($report['failed_commands'])->toBe(0);
});
