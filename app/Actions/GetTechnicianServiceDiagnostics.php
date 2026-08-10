<?php

namespace App\Actions;

use App\Contracts\Action;
use App\Models\CurrentSession;
use App\Models\DeviceMetric;
use App\Models\NetworkCommand;
use App\Models\Service;
use App\Models\UsageDaily;
use Carbon\CarbonImmutable;

final readonly class GetTechnicianServiceDiagnostics implements Action
{
    /** @return array<string, mixed> */
    public function handle(Service $service): array
    {
        $service->loadMissing('plan');
        $session = CurrentSession::query()->where('service_id', $service->id)->whereNull('stopped_at')->latest('last_seen_at')->first();
        $usage = UsageDaily::query()->where('service_id', $service->id)->whereDate('usage_date', '>=', CarbonImmutable::now()->subDay()->toDateString())->orderBy('usage_date')->get();
        $commands = NetworkCommand::query()->where('service_id', $service->id)->latest('id')->limit(10)->get();
        $metrics = $service->router_id === null
            ? collect()
            : DeviceMetric::query()->where('router_id', $service->router_id)->latest('observed_at')->limit(10)->get();

        return [
            'service' => [
                'id' => $service->public_id,
                'username' => $service->username,
                'status' => $service->status->value,
                'network_state' => $service->network_state->value,
                'expires_at' => $service->expires_at?->toIso8601String(),
                'plan' => $service->plan?->name,
            ],
            'live_session' => $session === null ? null : [
                'username' => $session->username,
                'acct_session_id' => $session->acct_session_id,
                'nasname' => $session->nasname,
                'framed_ip' => $session->framed_ip,
                'started_at' => $session->acct_start_time?->toIso8601String(),
                'last_seen_at' => $session->last_seen_at?->toIso8601String(),
                'input_octets' => $session->input_octets,
                'output_octets' => $session->output_octets,
            ],
            'usage_last_24h' => $usage->map(fn (UsageDaily $row): array => [
                'usage_date' => $row->usage_date->toDateString(),
                'input_octets' => $row->input_octets,
                'output_octets' => $row->output_octets,
                'total_octets' => $row->total_octets,
            ])->values()->all(),
            'router_health' => $metrics->map(fn (DeviceMetric $metric): array => [
                'status' => $metric->status,
                'latency_ms' => $metric->latency_ms,
                'observed_at' => $metric->observed_at->toIso8601String(),
            ])->values()->all(),
            'recent_commands' => $commands->map(fn (NetworkCommand $command): array => [
                'id' => $command->public_id,
                'action' => $command->action,
                'status' => $command->status,
                'attempts' => $command->attempts,
                'last_error' => $command->last_error,
                'completed_at' => $command->completed_at?->toIso8601String(),
            ])->values()->all(),
        ];
    }
}
