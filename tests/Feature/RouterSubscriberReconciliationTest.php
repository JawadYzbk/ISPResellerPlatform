<?php

use App\Actions\ReconcileRouterSubscribers;
use App\Domain\Network\SubscriberReader;
use App\Enums\ServiceStatus;
use App\Models\Router;
use App\Models\Service;
use App\Models\Tenant;
use App\Support\Tenancy;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('reports platform and router subscriber drift without changing services', function (): void {
    $tenant = Tenant::create(['name' => 'Northline', 'slug' => 'northline', 'base_currency' => 'USD', 'collection_currency' => 'USD']);
    app(Tenancy::class)->set($tenant);
    $router = Router::create(['name' => 'Core', 'host' => 'router.example.test', 'username' => 'api', 'password_encrypted' => 'secret']);
    $platformService = Service::factory()->create(['router_id' => $router->id, 'username' => 'ada.home', 'status' => ServiceStatus::Active]);
    app()->instance(SubscriberReader::class, new class implements SubscriberReader
    {
        public function read(Router $router): array
        {
            return [['name' => 'unknown.home', 'disabled' => 'false']];
        }
    });

    $result = app(ReconcileRouterSubscribers::class)->handle($router);

    expect($result['status'])->toBe('drifted')
        ->and($result['platform_only'])->toContain($platformService->username)
        ->and($result['router_only'])->toContain('unknown.home')
        ->and($platformService->refresh()->status)->toBe(ServiceStatus::Active);
});
