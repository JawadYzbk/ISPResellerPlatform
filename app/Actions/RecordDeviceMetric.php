<?php

namespace App\Actions;

use App\Contracts\Action;
use App\Models\DeviceMetric;
use App\Models\Router;
use Carbon\CarbonImmutable;

final readonly class RecordDeviceMetric implements Action
{
    /** @param array<string, mixed> $payload */
    public function handle(Router $router, string $status, array $payload = [], ?CarbonImmutable $at = null): DeviceMetric
    {
        return DeviceMetric::create([
            'router_id' => $router->id,
            'metric' => 'router_health',
            'status' => $status,
            'payload' => $payload,
            'observed_at' => $at ?? CarbonImmutable::now(),
        ]);
    }
}
