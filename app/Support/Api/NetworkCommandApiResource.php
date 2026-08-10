<?php

namespace App\Support\Api;

use App\Models\NetworkCommand;

final class NetworkCommandApiResource
{
    /** @return array<string, mixed> */
    public function make(NetworkCommand $command): array
    {
        $command->loadMissing(['service.customer']);
        $service = $command->service;

        return [
            'id' => $command->public_id,
            'action' => $command->action,
            'status' => $command->status,
            'attempts' => $command->attempts,
            'desired_state_version' => $command->desired_state_version,
            'available_at' => $command->available_at?->toIso8601String(),
            'started_at' => $command->started_at?->toIso8601String(),
            'completed_at' => $command->completed_at?->toIso8601String(),
            'last_error' => $command->last_error,
            'result' => $command->result,
            'service' => $service === null ? null : [
                'id' => $service->public_id,
                'username' => $service->username,
                'network_state' => $service->network_state->value,
                'customer' => $service->customer === null ? null : [
                    'id' => $service->customer->public_id,
                    'name' => $service->customer->full_name,
                ],
            ],
        ];
    }
}
