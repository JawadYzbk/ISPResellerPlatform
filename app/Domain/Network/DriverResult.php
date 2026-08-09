<?php

namespace App\Domain\Network;

final readonly class DriverResult
{
    /** @param array<string, mixed> $data */
    private function __construct(public string $status, public string $message, public array $data = []) {}

    /** @param array<string, mixed> $data */
    public static function success(string $message = 'ok', array $data = []): self
    {
        return new self('success', $message, $data);
    }

    /** @param array<string, mixed> $data */
    public static function pending(string $message, array $data = []): self
    {
        return new self('pending', $message, $data);
    }

    /** @param array<string, mixed> $data */
    public static function failure(string $message, array $data = []): self
    {
        return new self('failure', $message, $data);
    }
}
