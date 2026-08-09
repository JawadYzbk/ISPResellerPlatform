<?php

namespace App\Actions;

use App\Contracts\Action;
use App\Domain\Services\ServiceStateMachine;
use App\Enums\ServiceStatus;
use App\Models\Service;
use App\Models\User;

final readonly class TransitionService implements Action
{
    public function __construct(private ServiceStateMachine $stateMachine) {}

    /** @param array<string, mixed> $metadata */
    public function handle(Service $service, ServiceStatus $target, ?User $actor = null, array $metadata = [], bool $explicitReactivation = false): Service
    {
        return $this->stateMachine->transition($service, $target, $actor, $metadata, $explicitReactivation);
    }
}
