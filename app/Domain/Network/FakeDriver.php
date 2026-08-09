<?php

namespace App\Domain\Network;

use App\Models\NetworkCommand;
use App\Models\Service;

final class FakeDriver implements NetworkDriver
{
    /** @param array<string, DriverResult> $responses */
    public function __construct(private array $responses = []) {}

    public function execute(Service $service, NetworkCommand $command): DriverResult
    {
        return $this->responses[$command->action] ?? DriverResult::success('Fake driver completed.');
    }

    public function respondWith(string $action, DriverResult $result): self
    {
        $this->responses[$action] = $result;

        return $this;
    }
}
