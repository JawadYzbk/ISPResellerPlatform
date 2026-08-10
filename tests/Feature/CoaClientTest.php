<?php

use App\Domain\Radius\CoaClient;
use App\Domain\Radius\RadiusTransport;
use App\Models\Router;
use App\Models\Tenant;
use App\Support\Tenancy;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

final class FakeRadiusTransport implements RadiusTransport
{
    /** @var list<array{host: string, port: int, packet: string}> */
    public array $sent = [];

    public function __construct(private readonly int $responseCode = 41, private readonly string $secret = 'shared-secret') {}

    public function send(string $host, int $port, string $packet): ?string
    {
        $this->sent[] = ['host' => $host, 'port' => $port, 'packet' => $packet];

        $identifier = ord($packet[1]);
        $requestAuthenticator = substr($packet, 4, 16);
        $attributes = substr($packet, 20);
        $responseHeader = pack('CCn', $this->responseCode, $identifier, 20 + strlen($attributes));
        $responseAuthenticator = md5($responseHeader.$requestAuthenticator.$attributes.$this->secret, true);

        return $responseHeader.$responseAuthenticator.$attributes;
    }
}

it('sends a RADIUS Disconnect-Request to the configured router port', function (): void {
    $tenant = Tenant::create(['name' => 'Northline', 'slug' => 'northline', 'base_currency' => 'USD', 'collection_currency' => 'USD']);
    app(Tenancy::class)->set($tenant);
    $router = Router::create(['name' => 'Core', 'host' => 'radius.example.test', 'username' => 'api', 'password_encrypted' => 'router-secret', 'radius_secret_encrypted' => 'shared-secret', 'coa_port' => 1812]);
    $transport = new FakeRadiusTransport;
    app()->instance(RadiusTransport::class, $transport);

    $result = app(CoaClient::class)->disconnect($router, 'rami.1', 'session-001');

    expect($result->status)->toBe('ack')
        ->and($result->responseCode)->toBe(41)
        ->and($transport->sent[0]['host'])->toBe('radius.example.test')
        ->and($transport->sent[0]['port'])->toBe(1812)
        ->and(ord($transport->sent[0]['packet'][0]))->toBe(40)
        ->and($transport->sent[0]['packet'])->toContain('rami.1')
        ->and($transport->sent[0]['packet'])->toContain('session-001');
});

it('returns a negative result when the RADIUS server rejects CoA', function (): void {
    $tenant = Tenant::create(['name' => 'Southline', 'slug' => 'southline', 'base_currency' => 'USD', 'collection_currency' => 'USD']);
    app(Tenancy::class)->set($tenant);
    $router = Router::create(['name' => 'Core', 'host' => 'radius.example.test', 'username' => 'api', 'password_encrypted' => 'router-secret', 'radius_secret_encrypted' => 'shared-secret']);
    app()->instance(RadiusTransport::class, new FakeRadiusTransport(42));

    $result = app(CoaClient::class)->changeOfAuthorization($router, 'lina.2', null, [['type' => 11, 'value' => 'fup-1']]);

    expect($result->status)->toBe('nak')->and($result->responseCode)->toBe(42)->and($router->coa_port)->toBe(1700);
});
