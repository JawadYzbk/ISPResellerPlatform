<?php

namespace App\Domain\Network;

use App\Models\NetworkCommand;
use App\Models\Service;

final class NullDriver implements NetworkDriver
{
    public function execute(Service $service, NetworkCommand $command): DriverResult
    {
        return DriverResult::failure('No network driver is configured for provisioning mode.');
    }
}
