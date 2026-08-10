<?php

namespace App\Actions;

use App\Contracts\Action;
use App\Models\NetworkCommand;
use DomainException;

final readonly class RetryNetworkCommand implements Action
{
    public function __construct(private EnqueueNetworkCommand $enqueue) {}

    public function handle(NetworkCommand $command): NetworkCommand
    {
        if (! in_array($command->status, ['failed', 'abandoned'], true)) {
            throw new DomainException('Only failed or abandoned network commands can be retried.');
        }
        $service = $command->service;
        if ($service === null || $service->tenant_id !== $command->tenant_id) {
            throw new DomainException('The command service is no longer available in this tenant.');
        }

        return $this->enqueue->handle($service, $command->action, [...($command->payload ?? []), 'retry_of' => $command->id]);
    }
}
