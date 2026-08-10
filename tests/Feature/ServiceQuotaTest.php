<?php

use App\Actions\EnforceServiceQuota;
use App\Enums\ServiceStatus;
use App\Models\Message;
use App\Models\MessageTemplate;
use App\Models\NetworkCommand;
use App\Models\Service;
use App\Models\Tenant;
use App\Models\UsageDaily;
use App\Support\Tenancy;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;

uses(RefreshDatabase::class);

it('blocks a service once its cycle quota is exceeded and queues one command', function (): void {
    Queue::fake();
    $tenant = Tenant::create(['name' => 'Northline', 'slug' => 'northline', 'base_currency' => 'USD', 'collection_currency' => 'USD']);
    app(Tenancy::class)->set($tenant);
    $service = Service::factory()->create(['status' => ServiceStatus::Active, 'expires_at' => CarbonImmutable::parse('2026-08-30')]);
    $service->plan->forceFill(['metadata' => ['quota_bytes' => 1000, 'fup_action' => 'block']])->save();
    UsageDaily::create(['service_id' => $service->id, 'usage_date' => '2026-08-10', 'input_octets' => 700, 'output_octets' => 400, 'total_octets' => 1100, 'rolled_up_at' => now()]);

    expect(app(EnforceServiceQuota::class)->handle($tenant, CarbonImmutable::parse('2026-08-10')))->toBe(1)
        ->and($service->refresh()->status)->toBe(ServiceStatus::Suspended)
        ->and($service->current_period_bytes)->toBe(1100)
        ->and(NetworkCommand::count())->toBe(1);
});

it('marks throttle FUP once and does not duplicate it on replay', function (): void {
    Queue::fake();
    $tenant = Tenant::create(['name' => 'Southline', 'slug' => 'southline', 'base_currency' => 'USD', 'collection_currency' => 'USD']);
    app(Tenancy::class)->set($tenant);
    $service = Service::factory()->create(['status' => ServiceStatus::Active, 'expires_at' => CarbonImmutable::parse('2026-08-30')]);
    $service->plan->forceFill(['metadata' => ['quota_bytes' => 1000, 'fup_action' => 'throttle']])->save();
    UsageDaily::create(['service_id' => $service->id, 'usage_date' => '2026-08-10', 'input_octets' => 800, 'output_octets' => 400, 'total_octets' => 1200, 'rolled_up_at' => now()]);

    expect(app(EnforceServiceQuota::class)->handle($tenant, CarbonImmutable::parse('2026-08-10')))->toBe(1)
        ->and(app(EnforceServiceQuota::class)->handle($tenant, CarbonImmutable::parse('2026-08-10')))->toBe(0)
        ->and($service->refresh()->status)->toBe(ServiceStatus::Active)
        ->and(NetworkCommand::count())->toBe(1)
        ->and(NetworkCommand::firstOrFail()->action)->toBe('throttle');
});

it('queues each configured quota warning threshold once', function (): void {
    Queue::fake();
    $tenant = Tenant::create(['name' => 'Eastline', 'slug' => 'eastline', 'base_currency' => 'USD', 'collection_currency' => 'USD']);
    app(Tenancy::class)->set($tenant);
    MessageTemplate::create(['key' => 'service.quota_warning', 'channel' => 'sms', 'locale' => 'en', 'body' => '{{ customer_name }} used {{ quota_percent }}% of the quota for {{ service_username }}.']);
    $service = Service::factory()->create(['status' => ServiceStatus::Active, 'expires_at' => CarbonImmutable::parse('2026-08-30')]);
    $service->plan->forceFill(['metadata' => ['quota_bytes' => 1000, 'quota_warning_thresholds' => [0.8, 0.95], 'fup_action' => 'throttle']])->save();
    UsageDaily::create(['service_id' => $service->id, 'usage_date' => '2026-08-10', 'input_octets' => 1000, 'output_octets' => 0, 'total_octets' => 1000, 'rolled_up_at' => now()]);

    app(EnforceServiceQuota::class)->handle($tenant, CarbonImmutable::parse('2026-08-10'));
    app(EnforceServiceQuota::class)->handle($tenant, CarbonImmutable::parse('2026-08-10'));

    expect(Message::count())->toBe(2);
});
