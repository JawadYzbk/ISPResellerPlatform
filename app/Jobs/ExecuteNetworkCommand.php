<?php

namespace App\Jobs;

use App\Domain\Network\DriverManager;
use App\Enums\NetworkState;
use App\Models\NetworkCommand;
use Illuminate\Contracts\Queue\ShouldQueue;
use RuntimeException;

final class ExecuteNetworkCommand extends TenantAwareJob implements ShouldQueue
{
    public int $tries = 3;

    public function __construct(public int $commandId, ?int $tenantId = null)
    {
        parent::__construct($tenantId);
    }

    /** @return list<int> */
    public function backoff(): array
    {
        return [10, 60, 300];
    }

    public function handle(DriverManager $drivers): void
    {
        $command = NetworkCommand::query()->with('service')->findOrFail($this->commandId);
        $service = $command->service;
        if ($command->desired_state_version !== $service->desired_state_version) {
            $command->forceFill(['status' => 'stale', 'completed_at' => now(), 'last_error' => 'Superseded by a newer desired state.'])->save();

            return;
        }

        $command->forceFill(['status' => 'running', 'attempts' => $command->attempts + 1, 'started_at' => now()])->save();
        $result = $drivers->for($service)->execute($service, $command);
        $status = $result->status;
        $command->forceFill(['status' => match ($status) {
            'success' => 'completed', 'pending' => 'awaiting_confirmation', default => $command->attempts >= $this->tries ? 'abandoned' : 'failed'
        }, 'result' => $result->data, 'last_error' => $status === 'failure' ? $result->message : null, 'completed_at' => in_array($status, ['success', 'pending'], true) || $command->attempts >= $this->tries ? now() : null])->save();

        if ($status === 'success') {
            $service->forceFill(['network_state' => NetworkState::InSync])->save();
        } elseif ($status === 'failure') {
            $service->forceFill(['network_state' => NetworkState::Drifted])->save();
            if ($command->attempts < $this->tries) {
                throw new RuntimeException($result->message);
            }
        }
    }
}
