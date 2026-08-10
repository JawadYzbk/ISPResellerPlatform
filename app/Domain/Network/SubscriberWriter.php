<?php

namespace App\Domain\Network;

use App\Models\Router;

interface SubscriberWriter
{
    public function enable(Router $router, string $deviceId): void;
}
