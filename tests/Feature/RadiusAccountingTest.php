<?php

use App\Actions\CloseSessionsForNas;
use App\Actions\MarkStaleSessions;
use App\Actions\RollupDailyUsage;
use App\Actions\SyncRadiusAccounting;
use App\Actions\UpsertCurrentSession;
use App\Models\CurrentSession;
use App\Models\RadiusNas;
use App\Models\Service;
use App\Models\Tenant;
use App\Models\UsageDaily;
use App\Support\Tenancy;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;

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

it('imports stock radacct rows into tenant sessions and usage', function (): void {
    $tenant = Tenant::create(['name' => 'Radacctline', 'slug' => 'radacctline', 'base_currency' => 'USD', 'collection_currency' => 'USD']);
    app(Tenancy::class)->set($tenant);
    $service = Service::factory()->create(['username' => 'radius-customer-001']);
    RadiusNas::create(['nasname' => '10.0.0.1', 'shortname' => 'Core NAS', 'secret' => 'shared-secret', 'coa_port' => 1700]);
    $date = CarbonImmutable::parse('2026-08-10 12:00:00');

    DB::table('radacct')->insert([
        'acctsessionid' => 'acct-radacct-001',
        'acctuniqueid' => 'unique-radacct-001',
        'username' => $service->username,
        'nasipaddress' => '10.0.0.1',
        'acctstarttime' => $date,
        'acctupdatetime' => $date->addMinutes(5),
        'acctinputoctets' => 100,
        'acctoutputoctets' => 250,
        'framedipaddress' => '10.0.0.5',
    ]);

    expect(app(SyncRadiusAccounting::class)->handle($tenant, $date->addMinutes(5)))->toBe(1)
        ->and(CurrentSession::firstOrFail()->input_octets)->toBe(100)
        ->and(CurrentSession::firstOrFail()->output_octets)->toBe(250)
        ->and(DB::table('radacct')->where('acctuniqueid', 'unique-radacct-001')->value('service_id'))->toBe($service->id);

    app(RollupDailyUsage::class)->handle($tenant, $date);

    expect(UsageDaily::firstOrFail()->total_octets)->toBe(350);
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
