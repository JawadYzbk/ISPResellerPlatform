<?php

use App\Actions\MarkStaleSessions;
use App\Actions\RollupDailyUsage;
use App\Actions\UpsertCurrentSession;
use App\Models\CurrentSession;
use App\Models\Service;
use App\Models\Tenant;
use App\Models\UsageDaily;
use App\Support\Tenancy;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('upserts live RADIUS sessions and rolls usage up idempotently', function (): void {
    $tenant = Tenant::create(['name' => 'Northline', 'slug' => 'northline', 'base_currency' => 'USD', 'collection_currency' => 'USD']);
    app(Tenancy::class)->set($tenant);
    $service = Service::factory()->create();
    $date = CarbonImmutable::parse('2026-08-10 12:00:00');

    app(UpsertCurrentSession::class)->handle($service, 'acct-001', 'router-1', $date, 100, 250, '10.0.0.5', $date->startOfDay());
    app(UpsertCurrentSession::class)->handle($service, 'acct-001', 'router-1', $date->addMinutes(5), 150, 300, '10.0.0.5', $date->startOfDay());
    app(RollupDailyUsage::class)->handle($tenant, $date);
    app(RollupDailyUsage::class)->handle($tenant, $date);

    expect(CurrentSession::count())->toBe(1)
        ->and(UsageDaily::count())->toBe(1)
        ->and(UsageDaily::firstOrFail()->total_octets)->toBe(450);
});

it('marks sessions stale after two missed interim intervals', function (): void {
    $tenant = Tenant::create(['name' => 'Southline', 'slug' => 'southline', 'base_currency' => 'USD', 'collection_currency' => 'USD']);
    app(Tenancy::class)->set($tenant);
    $service = Service::factory()->create();
    $old = CarbonImmutable::parse('2026-08-10 11:00:00');
    app(UpsertCurrentSession::class)->handle($service, 'acct-002', 'router-1', $old, startedAt: $old);

    expect(app(MarkStaleSessions::class)->handle($tenant, 300, CarbonImmutable::parse('2026-08-10 11:11:00')))->toBe(1)
        ->and(CurrentSession::firstOrFail()->refresh()->terminate_cause)->toBe('Stale');
});
