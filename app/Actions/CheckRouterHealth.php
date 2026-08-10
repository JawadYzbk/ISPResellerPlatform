<?php

namespace App\Actions;

use App\Contracts\Action;
use App\Enums\IncidentStatus;
use App\Models\Incident;
use App\Models\Router;
use DomainException;

final readonly class CheckRouterHealth implements Action
{
    public function __construct(private TestRouterConnection $connection, private RecordDeviceMetric $recordMetric, private BroadcastIncidentNotification $broadcast) {}

    public function handle(Router $router, int $failureThreshold = 3): ?Incident
    {
        try {
            $result = $this->connection->handle($router);
            $this->recordMetric->handle($router, 'online', $result);
            $router->forceFill(['status' => 'online', 'metadata' => [...($router->metadata ?? []), 'consecutive_failures' => 0]])->save();
            $incidents = Incident::query()->where('router_id', $router->id)->where('type', 'router_unreachable')->where('status', '!=', IncidentStatus::Resolved)->get();
            foreach ($incidents as $incident) {
                $incident->forceFill(['status' => IncidentStatus::Resolved, 'resolved_at' => now()])->save();
                $this->broadcast->handle($incident, 'outage.resolved');
            }

            return null;
        } catch (DomainException $exception) {
            $this->recordMetric->handle($router, 'offline', ['error' => $exception->getMessage()]);
            $failures = ((int) ($router->metadata['consecutive_failures'] ?? 0)) + 1;
            $router->forceFill(['status' => 'offline', 'metadata' => [...($router->metadata ?? []), 'consecutive_failures' => $failures]])->save();
            if ($failures < max(1, $failureThreshold)) {
                return null;
            }

            $incident = Incident::firstOrCreate(
                ['router_id' => $router->id, 'type' => 'router_unreachable', 'status' => IncidentStatus::Open],
                ['severity' => 'critical', 'title' => 'Router unreachable: '.$router->name, 'description' => $exception->getMessage(), 'opened_at' => now(), 'metadata' => array_filter(['failure_count' => $failures, 'pop_id' => $router->pop_id], static fn (mixed $value): bool => $value !== null)],
            );

            if ($incident->wasRecentlyCreated) {
                $this->broadcast->handle($incident, 'outage.notice');
            }

            return $incident;
        }
    }
}
