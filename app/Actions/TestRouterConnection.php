<?php

namespace App\Actions;

use App\Contracts\Action;
use App\Models\Router;
use DomainException;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;

final readonly class TestRouterConnection implements Action
{
    /** @return array{status: string, version: string|null, identity: string|null} */
    public function handle(Router $router): array
    {
        $response = null;
        try {
            $response = Http::withBasicAuth($router->username, (string) $router->password_encrypted)
                ->withOptions(['verify' => $router->tls_verify])
                ->timeout(5)
                ->get($router->baseUrl().'/rest/system/resource');
        } catch (ConnectionException $exception) {
            throw new DomainException('router_unreachable: '.$exception->getMessage(), previous: $exception);
        }

        if ($response->status() === 401 || $response->status() === 403) {
            throw new DomainException('router_credentials_rejected');
        }
        if ($response->failed()) {
            throw new DomainException('router_api_error: HTTP '.$response->status());
        }

        $resource = $response->json();
        $router->forceFill(['status' => 'online', 'last_seen_at' => now()])->save();

        return [
            'status' => 'online',
            'version' => is_array($resource) ? (isset($resource['version']) ? (string) $resource['version'] : null) : null,
            'identity' => is_array($resource) ? (isset($resource['board-name']) ? (string) $resource['board-name'] : null) : null,
        ];
    }
}
