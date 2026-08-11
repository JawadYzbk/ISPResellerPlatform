<?php

use App\Actions\CloseSessionsForNas;
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

it('closes all active sessions for a NAS accounting-on or accounting-off event', function (): void {
    $tenant = Tenant::create(['name' => 'Eastline', 'slug' => 'eastline', 'base_currency' => 'USD', 'collection_currency' => 'USD']);
    app(Tenancy::class)->set($tenant);
    $service = Service::factory()->create();
    $otherService = Service::factory()->create();
    $at = CarbonImmutable::parse('2026-08-10 12:00:00');

    app(UpsertCurrentSession::class)->handle($service, 'acct-nas-001', 'nas-01', $at, startedAt: $at);
    app(UpsertCurrentSession::class)->handle($otherService, 'acct-nas-002', 'nas-01', $at, startedAt: $at);
    app(UpsertCurrentSession::class)->handle($service, 'acct-other-001', 'nas-02', $at, startedAt: $at);

    expect(app(CloseSessionsForNas::class)->handle($tenant, 'nas-01', $at->addMinute(), 'NAS-Reboot'))->toBe(2)
        ->and(app(CloseSessionsForNas::class)->handle($tenant, 'nas-01', $at->addMinutes(2), 'NAS-Reboot'))->toBe(0)
        ->and(CurrentSession::query()->where('nasname', 'nas-01')->whereNull('stopped_at')->count())->toBe(0)
        ->and(CurrentSession::query()->where('nasname', 'nas-02')->whereNull('stopped_at')->count())->toBe(1)
        ->and(CurrentSession::query()->where('nasname', 'nas-01')->value('terminate_cause'))->toBe('NAS-Reboot');
});
