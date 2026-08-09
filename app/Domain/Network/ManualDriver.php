<?php

namespace App\Domain\Network;

use App\Models\NetworkCommand;
use App\Models\Service;

final class ManualDriver implements NetworkDriver
{
    public function execute(Service $service, NetworkCommand $command): DriverResult
    {
        return DriverResult::pending('Manual confirmation required.', ['service_id' => $service->public_id, 'action' => $command->action]);
    }
}
