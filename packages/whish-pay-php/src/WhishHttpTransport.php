<?php

declare(strict_types=1);

namespace WhishPay;

interface WhishHttpTransport
{
    /**
     * @param array<string, string> $headers
     * @param array<string, mixed>|null $payload
     */
    public function send(string $method, string $url, array $headers, ?array $payload, int $timeout): WhishHttpResponse;
}
