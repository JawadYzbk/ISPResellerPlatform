<?php

namespace App\Actions;

use App\Contracts\Action;
use App\Jobs\ExecuteNetworkCommand;
use App\Models\NetworkCommand;
use App\Models\OutboxEvent;
use App\Models\Service;
use Illuminate\Support\Facades\DB;

final readonly class EnqueueNetworkCommand implements Action
{
    /** @param array<string, mixed> $payload */
    public function handle(Service $service, string $action, array $payload = []): NetworkCommand
    {
        return DB::transaction(function () use ($service, $action, $payload): NetworkCommand {
            $service = Service::query()->lockForUpdate()->findOrFail($service->id);
            $service->increment('desired_state_version');
            $service->refresh();
            $command = NetworkCommand::create([
                'service_id' => $service->id,
                'action' => $action,
                'status' => 'pending',
                'desired_state_version' => $service->desired_state_version,
                'available_at' => now(),
                'payload' => $payload,
            ]);
            OutboxEvent::create([
                'event_type' => 'network.command.created',
                'aggregate_type' => Service::class,
                'aggregate_id' => (string) $service->id,
                'payload' => ['command_id' => $command->id, 'desired_state_version' => $command->desired_state_version],
            ]);

            ExecuteNetworkCommand::dispatch($command->id)->afterCommit();

            return $command;
        });
    }
}
