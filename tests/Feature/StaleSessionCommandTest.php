<?php

use App\Actions\UpsertCurrentSession;
use App\Models\CurrentSession;
use App\Models\Service;
use App\Models\Tenant;
use App\Support\Tenancy;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('uses the tenant interim interval when closing stale sessions', function (): void {
    $tenant = Tenant::create(['name' => 'Northline', 'slug' => 'northline', 'base_currency' => 'USD', 'collection_currency' => 'USD', 'settings' => ['radius_interim_interval_seconds' => 60]]);
    app(Tenancy::class)->set($tenant);
    $service = Service::factory()->create();
    $old = CarbonImmutable::now()->subMinutes(200);
    app(UpsertCurrentSession::class)->handle($service, 'acct-command-001', 'router-1', $old, startedAt: $old);

    $this->artisan('radius:mark-stale-sessions')->assertSuccessful();

    expect(CurrentSession::firstOrFail()->refresh()->terminate_cause)->toBe('Stale');
});
