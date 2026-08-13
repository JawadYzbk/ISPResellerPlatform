<?php

namespace App\Domain\Communications;

final readonly class MessageDeliveryResult
{
    /** @param array<string, mixed> $metadata */
    private function __construct(public string $status, public string $provider, public ?string $providerMessageId, public string $message, public array $metadata = []) {}

    /** @param array<string, mixed> $metadata */
    public static function sent(string $provider, ?string $providerMessageId = null, array $metadata = []): self
    {
        return new self('sent', $provider, $providerMessageId, 'Message accepted by provider.', $metadata);
    }

    /** @param array<string, mixed> $metadata */
    public static function failed(string $provider, string $message, array $metadata = []): self
    {
        return new self('failed', $provider, null, $message, $metadata);
    }

    /** @param array<string, mixed> $metadata */
    public static function deferred(string $provider, string $message, int $retryAfter, array $metadata = []): self
    {
        return new self('deferred', $provider, null, $message, [...$metadata, 'retry_after' => max(1, $retryAfter)]);
    }

    /** @param array<string, mixed> $metadata */
    public function withMetadata(array $metadata): self
    {
        return new self($this->status, $this->provider, $this->providerMessageId, $this->message, [...$this->metadata, ...$metadata]);
    }
}
