<?php

namespace App\Actions;

use App\Contracts\Action;
use App\Domain\Radius\RadiusSyncService;
use App\Models\RadiusNas;
use App\Models\Router;

final readonly class UpdateRouter implements Action
{
    public function __construct(private RadiusSyncService $radiusSync) {}

    /** @param array<string, mixed> $data */
    public function handle(Router $router, array $data): Router
    {
        $previousHost = $router->host;
        $router->forceFill([
            'pop_id' => $data['pop_id'] ?? null,
            'name' => $data['name'],
            'host' => $data['host'],
            'api_port' => $data['api_port'],
            'username' => $data['username'],
            'coa_port' => $data['coa_port'],
            'tls_verify' => $data['tls_verify'],
        ]);
        if (filled($data['password'] ?? null)) {
            $router->password_encrypted = $data['password'];
        }
        if (filled($data['radius_secret'] ?? null)) {
            $router->radius_secret_encrypted = $data['radius_secret'];
        }
        $router->save();

        $updated = $router->refresh();
        $this->radiusSync->syncRouter($updated);
        if ($previousHost !== $updated->host) {
            RadiusNas::query()->where('tenant_id', $updated->tenant_id)->where('nasname', $previousHost)->delete();
        }

        return $updated;
    }
}
