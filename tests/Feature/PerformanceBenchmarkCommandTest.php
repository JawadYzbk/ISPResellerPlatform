<?php

use App\Models\CurrentSession;
use App\Models\Service;
use App\Models\Tenant;
use App\Models\UsageDaily;
use App\Support\Tenancy;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('benchmarks the tenant-scoped live session and usage query shapes', function (): void {
    $tenant = Tenant::create(['name' => 'Benchmark', 'slug' => 'benchmark', 'base_currency' => 'USD', 'collection_currency' => 'USD']);
    app(Tenancy::class)->set($tenant);
    $service = Service::factory()->create();
    CurrentSession::create([
        'service_id' => $service->id,
        'username' => $service->username,
        'acct_session_id' => 'benchmark-session-001',
        'nasname' => 'router-01',
        'last_seen_at' => now(),
    ]);
    UsageDaily::create([
        'service_id' => $service->id,
        'usage_date' => CarbonImmutable::today()->toDateString(),
        'input_octets' => 100,
        'output_octets' => 200,
        'total_octets' => 300,
        'rolled_up_at' => now(),
    ]);

    $this->artisan('platform:benchmark-usage', [
        '--tenant' => $tenant->slug,
        '--service' => $service->public_id,
        '--repetitions' => 1,
        '--json' => true,
    ])->assertSuccessful();
});
