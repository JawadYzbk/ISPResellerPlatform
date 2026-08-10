<?php

namespace App\Actions;

use App\Contracts\Action;
use App\Enums\IncidentStatus;
use App\Models\Incident;
use App\Models\Router;
use DomainException;

final readonly class CheckRouterHealth implements Action
{
    public function __construct(private TestRouterConnection $connection) {}

    public function handle(Router $router, int $failureThreshold = 3): ?Incident
    {
        try {
            $this->connection->handle($router);
            $router->forceFill(['status' => 'online', 'metadata' => [...($router->metadata ?? []), 'consecutive_failures' => 0]])->save();
            Incident::query()->where('router_id', $router->id)->where('type', 'router_unreachable')->where('status', '!=', IncidentStatus::Resolved)->update(['status' => IncidentStatus::Resolved, 'resolved_at' => now(), 'updated_at' => now()]);

            return null;
        } catch (DomainException $exception) {
            $failures = ((int) ($router->metadata['consecutive_failures'] ?? 0)) + 1;
            $router->forceFill(['status' => 'offline', 'metadata' => [...($router->metadata ?? []), 'consecutive_failures' => $failures]])->save();
            if ($failures < max(1, $failureThreshold)) {
                return null;
            }

            return Incident::firstOrCreate(
                ['router_id' => $router->id, 'type' => 'router_unreachable', 'status' => IncidentStatus::Open],
                ['severity' => 'critical', 'title' => 'Router unreachable: '.$router->name, 'description' => $exception->getMessage(), 'opened_at' => now(), 'metadata' => ['failure_count' => $failures]],
            );
        }
    }
}
