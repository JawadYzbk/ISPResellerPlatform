<?php

namespace App\Domain\Network;

use App\Models\NetworkCommand;
use App\Models\Service;

interface NetworkDriver
{
    public function execute(Service $service, NetworkCommand $command): DriverResult;
}
