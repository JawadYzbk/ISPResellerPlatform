<?php

namespace App\Support\Api;

use App\Models\Router;
use Carbon\CarbonImmutable;

final readonly class RouterApiResource
{
    /** @return array<string, mixed> */
    public function make(Router $router): array
    {
        $router->loadMissing('pop');
        $router->loadCount('services');

        return [
            'id' => $router->public_id,
            'name' => $router->name,
            'host' => $router->host,
            'api_port' => $router->api_port,
            'username' => $router->username,
            'coa_port' => $router->coa_port,
            'tls_verify' => $router->tls_verify,
            'status' => $router->status,
            'last_seen_at' => $router->last_seen_at === null ? null : CarbonImmutable::parse((string) $router->last_seen_at)->toIso8601String(),
            'consecutive_failures' => (int) ($router->metadata['consecutive_failures'] ?? 0),
            'services_count' => $router->services_count,
            'pop' => $router->pop === null ? null : [
                'name' => $router->pop->name,
                'code' => $router->pop->code,
            ],
        ];
    }
}
