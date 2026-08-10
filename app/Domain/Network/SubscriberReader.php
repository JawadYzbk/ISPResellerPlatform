<?php

namespace App\Domain\Network;

use App\Models\Router;

interface SubscriberReader
{
    /** @return list<array<string, mixed>> */
    public function read(Router $router): array;
}
