<?php

use App\Domain\Network\RadiusDriver;
use App\Domain\Radius\RadiusTransport;
use App\Enums\ProvisioningMode;
use App\Enums\ServiceStatus;
use App\Models\NetworkCommand;
use App\Models\RadiusUserGroup;
use App\Models\Router;
use App\Models\Service;
use App\Models\Tenant;
use App\Support\Tenancy;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

final class RadiusDriverFakeTransport implements RadiusTransport
{
    /** @var list<array{host: string, port: int, packet: string}> */
    public array $sent = [];

    public function send(string $host, int $port, string $packet): ?string
    {
        $this->sent[] = ['host' => $host, 'port' => $port, 'packet' => $packet];
        $identifier = ord($packet[1]);
        $requestAuthenticator = substr($packet, 4, 16);
        $attributes = substr($packet, 20);
        $header = pack('CCn', 41, $identifier, 20 + strlen($attributes));

        return $header.md5($header.$requestAuthenticator.$attributes.'shared-secret', true).$attributes;
    }
}

it('syncs RADIUS state and disconnects a suspended live session through CoA', function (): void {
    $tenant = Tenant::create(['name' => 'Northline', 'slug' => 'northline', 'base_currency' => 'USD', 'collection_currency' => 'USD']);
    app(Tenancy::class)->set($tenant);
    $router = Router::create(['name' => 'Core', 'host' => 'radius.example.test', 'username' => 'api', 'password_encrypted' => 'router-secret', 'radius_secret_encrypted' => 'shared-secret']);
    $service = Service::factory()->create(['router_id' => $router->id, 'provisioning_mode' => ProvisioningMode::Radius, 'status' => ServiceStatus::Suspended]);
    $command = NetworkCommand::create(['service_id' => $service->id, 'action' => 'suspend', 'desired_state_version' => 1, 'status' => 'pending', 'payload' => ['session_id' => 'session-001']]);
    $transport = new RadiusDriverFakeTransport;
    app()->instance(RadiusTransport::class, $transport);

    $result = app(RadiusDriver::class)->execute($service, $command);

    expect($result->status)->toBe('success')
        ->and($result->data['coa_status'])->toBe('ack')
        ->and(RadiusUserGroup::query()->where('service_id', $service->id)->value('groupname'))->toBe('suspended')
        ->and(ord($transport->sent[0]['packet'][0]))->toBe(40);
});

it('keeps a RADIUS service successful when no router is configured for a non-live action', function (): void {
    $tenant = Tenant::create(['name' => 'Southline', 'slug' => 'southline', 'base_currency' => 'USD', 'collection_currency' => 'USD']);
    app(Tenancy::class)->set($tenant);
    $service = Service::factory()->create(['provisioning_mode' => ProvisioningMode::Radius, 'status' => ServiceStatus::Active]);
    $command = NetworkCommand::create(['service_id' => $service->id, 'action' => 'activate', 'desired_state_version' => 1, 'status' => 'pending']);

    $result = app(RadiusDriver::class)->execute($service, $command);

    expect($result->status)->toBe('success')->and($result->data['coa_status'])->toBe('not_required');
});
