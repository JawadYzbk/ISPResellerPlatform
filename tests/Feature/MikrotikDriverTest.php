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
    Http::fake([
        'https://router.example.test/rest/ppp/secret/add' => Http::response(['.id' => '*1'], 201),
        '*' => Http::response([], 200),
    ]);
    $tenant = Tenant::create(['name' => 'Northline', 'slug' => 'northline', 'base_currency' => 'USD', 'collection_currency' => 'USD']);
    app(Tenancy::class)->set($tenant);
    $router = Router::create(['name' => 'Core', 'host' => 'router.example.test', 'username' => 'api', 'password_encrypted' => 'secret']);
    $service = Service::factory()->create(['router_id' => $router->id, 'provisioning_mode' => ProvisioningMode::Mikrotik]);
    $command = NetworkCommand::create(['service_id' => $service->id, 'action' => 'activate', 'desired_state_version' => 1, 'status' => 'pending']);

    $result = app(MikrotikApiDriver::class)->execute($service, $command);

    expect($result->status)->toBe('success')
        ->and($result->data['action'])->toBe('activate')
        ->and($result->data['routeros_id'])->toBe('*1')
        ->and($result->data)->not->toHaveKey('response')
        ->and($service->refresh()->metadata['routeros_id'])->toBe('*1');
    Http::assertSent(fn ($request): bool => $request->url() === 'https://router.example.test/rest/ppp/secret/add'
        && $request->data()['name'] === $service->username
        && $request->data()['comment'] === 'svc:'.$service->public_id
        && $request->data()['service'] === 'pppoe');
});

it('applies a configured RouterOS FUP profile for throttle commands', function (): void {
    Http::fake(['https://router.example.test/rest/ppp/secret/%2A1' => Http::response(['.id' => '*1'], 200)]);
    $tenant = Tenant::create(['name' => 'Southline', 'slug' => 'southline', 'base_currency' => 'USD', 'collection_currency' => 'USD']);
    app(Tenancy::class)->set($tenant);
    $router = Router::create(['name' => 'Core', 'host' => 'router.example.test', 'username' => 'api', 'password_encrypted' => 'secret']);
    $service = Service::factory()->create(['router_id' => $router->id, 'provisioning_mode' => ProvisioningMode::Mikrotik, 'metadata' => ['routeros_id' => '*1']]);
    $command = NetworkCommand::create(['service_id' => $service->id, 'action' => 'throttle', 'desired_state_version' => 1, 'status' => 'pending', 'payload' => ['fup_profile' => 'fup-1']]);

    $result = app(MikrotikApiDriver::class)->execute($service, $command);

    expect($result->status)->toBe('success');
    Http::assertSent(fn ($request): bool => $request->url() === 'https://router.example.test/rest/ppp/secret/%2A1'
        && $request->data()['profile'] === 'fup-1');
});

it('applies a changed RouterOS plan profile without recreating the subscriber', function (): void {
    Http::fake(['https://router.example.test/rest/ppp/secret/%2A1' => Http::response(['.id' => '*1'], 200)]);
    $tenant = Tenant::create(['name' => 'Westline', 'slug' => 'westline', 'base_currency' => 'USD', 'collection_currency' => 'USD']);
    app(Tenancy::class)->set($tenant);
    $router = Router::create(['name' => 'Core', 'host' => 'router.example.test', 'username' => 'api', 'password_encrypted' => 'secret']);
    $service = Service::factory()->create(['router_id' => $router->id, 'provisioning_mode' => ProvisioningMode::Mikrotik, 'metadata' => ['routeros_id' => '*1']]);
    $command = NetworkCommand::create(['service_id' => $service->id, 'action' => 'change_plan', 'desired_state_version' => 1, 'status' => 'pending']);

    $result = app(MikrotikApiDriver::class)->execute($service, $command);

    expect($result->status)->toBe('success');
    Http::assertSent(fn ($request): bool => $request->method() === 'PATCH'
        && $request->url() === 'https://router.example.test/rest/ppp/secret/%2A1'
        && $request->data()['profile'] === 'plan-'.$service->plan->slug);
});

it('disconnects active RouterOS sessions by returned device id', function (): void {
    Http::fake([
        'https://router.example.test/rest/ppp/active?name=*' => Http::response([['.id' => '*9']], 200),
        'https://router.example.test/rest/ppp/active/%2A9' => Http::response([], 200),
    ]);
    $tenant = Tenant::create(['name' => 'Eastline', 'slug' => 'eastline', 'base_currency' => 'USD', 'collection_currency' => 'USD']);
    app(Tenancy::class)->set($tenant);
    $router = Router::create(['name' => 'Core', 'host' => 'router.example.test', 'username' => 'api', 'password_encrypted' => 'secret']);
    $service = Service::factory()->create(['router_id' => $router->id, 'provisioning_mode' => ProvisioningMode::Mikrotik]);
    $command = NetworkCommand::create(['service_id' => $service->id, 'action' => 'disconnect', 'desired_state_version' => 1, 'status' => 'pending']);

    $result = app(MikrotikApiDriver::class)->execute($service, $command);

    expect($result->status)->toBe('success')
        ->and($result->data['disconnected_sessions'])->toBe(1)
        ->and($result->data)->not->toHaveKey('response');
    Http::assertSent(fn ($request): bool => $request->method() === 'DELETE' && $request->url() === 'https://router.example.test/rest/ppp/active/%2A9');
});
