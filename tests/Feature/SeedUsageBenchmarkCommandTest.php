<?php

use App\Models\CurrentSession;
use App\Models\Service;
use App\Models\Tenant;
use App\Models\UsageDaily;
use App\Support\Tenancy;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('seeds a small reproducible benchmark tenant without duplicating rows', function (): void {
    $tenant = Tenant::create(['name' => 'Benchmark', 'slug' => 'benchmark', 'base_currency' => 'USD', 'collection_currency' => 'USD']);

    $this->artisan('platform:seed-usage-benchmark', ['--tenant' => $tenant->slug, '--count' => 3, '--usage-days' => 2, '--yes' => true])
        ->assertExitCode(0);

    app(Tenancy::class)->set($tenant);
    expect(Service::query()->where('username', 'like', 'bench-%')->count())->toBe(3)
        ->and(CurrentSession::query()->count())->toBe(3)
        ->and(UsageDaily::query()->count())->toBe(6);

    $this->artisan('platform:seed-usage-benchmark', ['--tenant' => $tenant->slug, '--count' => 3, '--usage-days' => 2, '--yes' => true])
        ->assertExitCode(0);

    app(Tenancy::class)->set($tenant);
    expect(Service::query()->where('username', 'like', 'bench-%')->count())->toBe(3)
        ->and(CurrentSession::query()->count())->toBe(3)
        ->and(UsageDaily::query()->count())->toBe(6);
});
