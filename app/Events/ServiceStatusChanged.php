<?php

namespace App\Events;

use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Contracts\Events\ShouldDispatchAfterCommit;

final class ServiceStatusChanged implements ShouldBroadcast, ShouldDispatchAfterCommit
{
    public function __construct(
        public readonly string $tenantPublicId,
        public readonly string $serviceId,
        public readonly string $fromStatus,
        public readonly string $toStatus,
    ) {}

    public function broadcastOn(): array
    {
        return [new PrivateChannel('tenant.'.$this->tenantPublicId)];
    }

    public function broadcastAs(): string
    {
        return 'service.status.changed';
    }

    /** @return array<string, string> */
    public function broadcastWith(): array
    {
        return ['service_id' => $this->serviceId, 'from_status' => $this->fromStatus, 'to_status' => $this->toStatus];
    }
}
