<?php

use App\Actions\CheckRouterHealth;
use App\Actions\PruneDeviceMetrics;
use App\Models\DeviceMetric;
use App\Models\Router;
use App\Models\Tenant;
use App\Support\Tenancy;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;

uses(RefreshDatabase::class);

it('records router health observations and prunes only expired metrics', function (): void {
    Http::fake(['https://router.example.test:443/rest/system/resource' => ['version' => '7.15.2', 'board-name' => 'CHR']]);
    $tenant = Tenant::create(['name' => 'Northline', 'slug' => 'northline', 'base_currency' => 'USD', 'collection_currency' => 'USD']);
    app(Tenancy::class)->set($tenant);
    $router = Router::create(['name' => 'Core', 'host' => 'router.example.test', 'username' => 'api', 'password_encrypted' => 'secret']);

    app(CheckRouterHealth::class)->handle($router);
    DeviceMetric::create(['router_id' => $router->id, 'metric' => 'router_health', 'status' => 'online', 'observed_at' => CarbonImmutable::now()->subDays(100)]);

    expect(DeviceMetric::count())->toBe(2)
        ->and(app(PruneDeviceMetrics::class)->handle($tenant, 90, CarbonImmutable::now()))->toBe(1)
        ->and(DeviceMetric::count())->toBe(1);
});
