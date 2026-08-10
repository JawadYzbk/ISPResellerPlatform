<?php

namespace App\Actions;

use App\Contracts\Action;
use App\Models\CurrentSession;
use App\Models\Incident;
use App\Models\NetworkCommand;
use App\Models\Router;
use App\Models\Service;
use App\Models\WorkOrder;

final readonly class GetOperationsReport implements Action
{
    /** @return array<string, mixed> */
    public function handle(): array
    {
        return [
            'generated_at' => now()->toIso8601String(),
            'service_counts_by_status' => $this->counts(Service::query()->selectRaw('status, COUNT(*) as total')->groupBy('status')->pluck('total', 'status')->all()),
            'expiring_services' => Service::query()->whereBetween('expires_at', [now(), now()->addDays(7)])->count(),
            'work_order_counts_by_status' => $this->counts(WorkOrder::query()->selectRaw('status, COUNT(*) as total')->groupBy('status')->pluck('total', 'status')->all()),
            'incident_counts_by_status' => $this->counts(Incident::query()->selectRaw('status, COUNT(*) as total')->groupBy('status')->pluck('total', 'status')->all()),
            'active_sessions' => CurrentSession::query()->whereNull('stopped_at')->count(),
            'offline_routers' => Router::query()->where('status', 'offline')->count(),
            'network_drift' => Service::query()->whereIn('network_state', ['drifted', 'failed'])->count(),
            'failed_commands' => NetworkCommand::query()->where('status', 'failed')->count(),
        ];
    }

    /** @param array<mixed, mixed> $values @return array<string, int> */
    private function counts(array $values): array
    {
        $counts = [];
        foreach ($values as $key => $value) {
            $counts[(string) $key] = (int) $value;
        }

        return $counts;
    }
}
