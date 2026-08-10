<?php

namespace App\Domain\Radius;

interface RadiusTransport
{
    public function send(string $host, int $port, string $packet): ?string;
}
