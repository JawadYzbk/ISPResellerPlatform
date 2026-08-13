<?php

namespace App\Domain\Communications;

final readonly class WhatsAppDeliveryDecision
{
    private function __construct(public bool $allowed, public int $retryAfter, public string $reason) {}

    public static function allowed(): self
    {
        return new self(true, 0, 'allowed');
    }

    public static function deferred(int $retryAfter, string $reason): self
    {
        return new self(false, max(1, $retryAfter), $reason);
    }
}
