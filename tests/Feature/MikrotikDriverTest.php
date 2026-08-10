<?php

use App\Domain\Network\MikrotikApiDriver;
use App\Enums\ProvisioningMode;
use App\Models\NetworkCommand;
use App\Models\Router;
use App\Models\Service;
use App\Models\Tenant;
use App\Support\Tenancy;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;

uses(RefreshDatabase::class);

it('executes a bounded RouterOS REST command for a Mikrotik service', function (): void {
    Http::fake(['https://router.example.test/rest/ppp/secret/add' => Http::response(['.id' => '*1'], 201)]);
    $tenant = Tenant::create(['name' => 'Northline', 'slug' => 'northline', 'base_currency' => 'USD', 'collection_currency' => 'USD']);
    app(Tenancy::class)->set($tenant);
    $router = Router::create(['name' => 'Core', 'host' => 'router.example.test', 'username' => 'api', 'password_encrypted' => 'secret']);
    $service = Service::factory()->create(['router_id' => $router->id, 'provisioning_mode' => ProvisioningMode::Mikrotik]);
    $command = NetworkCommand::create(['service_id' => $service->id, 'action' => 'activate', 'desired_state_version' => 1, 'status' => 'pending']);

    $result = app(MikrotikApiDriver::class)->execute($service, $command);

    expect($result->status)->toBe('success')->and($result->data['action'])->toBe('activate');
    Http::assertSent(fn ($request): bool => $request->url() === 'https://router.example.test/rest/ppp/secret/add' && $request->data()['name'] === $service->username);
});

it('applies a configured RouterOS FUP profile for throttle commands', function (): void {
    Http::fake(['https://router.example.test/rest/ppp/secret/set' => Http::response(['.id' => '*1'], 200)]);
    $tenant = Tenant::create(['name' => 'Southline', 'slug' => 'southline', 'base_currency' => 'USD', 'collection_currency' => 'USD']);
    app(Tenancy::class)->set($tenant);
    $router = Router::create(['name' => 'Core', 'host' => 'router.example.test', 'username' => 'api', 'password_encrypted' => 'secret']);
    $service = Service::factory()->create(['router_id' => $router->id, 'provisioning_mode' => ProvisioningMode::Mikrotik]);
    $command = NetworkCommand::create(['service_id' => $service->id, 'action' => 'throttle', 'desired_state_version' => 1, 'status' => 'pending', 'payload' => ['fup_profile' => 'fup-1']]);

    expect(app(MikrotikApiDriver::class)->execute($service, $command)->status)->toBe('success');
    Http::assertSent(fn ($request): bool => $request->url() === 'https://router.example.test/rest/ppp/secret/set' && $request->data()['numbers'] === $service->username && $request->data()['profile'] === 'fup-1');
});
