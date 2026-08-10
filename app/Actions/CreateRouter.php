<?php

namespace App\Actions;

use App\Contracts\Action;
use App\Models\Router;
use App\Models\Tenant;

final readonly class CreateRouter implements Action
{
    /** @param array<string, mixed> $data */
    public function handle(array $data, Tenant $tenant): Router
    {
        return Router::create([
            'tenant_id' => $tenant->id,
            'pop_id' => $data['pop_id'] ?? null,
            'name' => $data['name'],
            'host' => $data['host'],
            'api_port' => $data['api_port'],
            'username' => $data['username'],
            'password_encrypted' => $data['password'],
            'radius_secret_encrypted' => $data['radius_secret'] ?? null,
            'coa_port' => $data['coa_port'],
            'tls_verify' => $data['tls_verify'],
        ]);
    }
}
