<?php

namespace App\Domain\Network;

use App\Models\Router;
use DomainException;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;

final class MikrotikSubscriberReader implements SubscriberReader, SubscriberWriter
{
    public function read(Router $router): array
    {
        try {
            $response = Http::withBasicAuth($router->username, (string) $router->password_encrypted)
                ->withOptions(['verify' => $router->tls_verify])
                ->timeout(10)
                ->get($router->baseUrl().'/rest/ppp/secret');
        } catch (ConnectionException $exception) {
            throw new DomainException('router_unreachable: '.$exception->getMessage(), previous: $exception);
        }

        if (in_array($response->status(), [401, 403], true)) {
            throw new DomainException('router_credentials_rejected');
        }
        if ($response->failed()) {
            throw new DomainException('router_api_error: HTTP '.$response->status());
        }

        $payload = $response->json();
        if (! is_array($payload)) {
            throw new DomainException('router_api_error: subscriber response was not a list');
        }

        return array_values(array_filter($payload, is_array(...)));
    }

    public function enable(Router $router, string $deviceId): void
    {
        try {
            $response = Http::withBasicAuth($router->username, (string) $router->password_encrypted)
                ->withOptions(['verify' => $router->tls_verify])
                ->timeout(10)
                ->patch($router->baseUrl().'/rest/ppp/secret/'.rawurlencode($deviceId), ['disabled' => 'false']);
        } catch (ConnectionException $exception) {
            throw new DomainException('router_unreachable: '.$exception->getMessage(), previous: $exception);
        }

        if (in_array($response->status(), [401, 403], true)) {
            throw new DomainException('router_credentials_rejected');
        }
        if ($response->failed()) {
            throw new DomainException('router_api_error: HTTP '.$response->status());
        }
    }
}
