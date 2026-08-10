<?php

use App\Actions\CheckRouterHealth;
use App\Enums\IncidentStatus;
use App\Models\Incident;
use App\Models\Router;
use App\Models\Tenant;
use App\Support\Tenancy;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;

uses(RefreshDatabase::class);

it('raises one incident after consecutive router failures and resolves on recovery', function (): void {
    Http::fakeSequence()->pushStatus(503)->pushStatus(503)->pushStatus(503)->push(['version' => '7.15.2', 'board-name' => 'CHR']);
    $tenant = Tenant::create(['name' => 'Northline', 'slug' => 'northline', 'base_currency' => 'USD', 'collection_currency' => 'USD']);
    app(Tenancy::class)->set($tenant);
    $router = Router::create(['name' => 'Core', 'host' => 'router.example.test', 'username' => 'api', 'password_encrypted' => 'secret']);

    expect(app(CheckRouterHealth::class)->handle($router, 3))->toBeNull()
        ->and(app(CheckRouterHealth::class)->handle($router, 3))->toBeNull()
        ->and(app(CheckRouterHealth::class)->handle($router, 3))->toBeInstanceOf(Incident::class)
        ->and(Incident::count())->toBe(1)
        ->and(app(CheckRouterHealth::class)->handle($router->refresh(), 3))->toBeNull()
        ->and(Incident::firstOrFail()->refresh()->status)->toBe(IncidentStatus::Resolved);
});
