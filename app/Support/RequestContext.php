<?php

namespace App\Support;

use Illuminate\Support\Str;

final class RequestContext
{
    private string $requestId;

    /** @var array<string, scalar|null> */
    private array $values = [];

    public function begin(?string $requestId = null): void
    {
        $this->requestId = $requestId ?: (string) Str::uuid();
        $this->values = ['request_id' => $this->requestId];
    }

    public function requestId(): string
    {
        return $this->requestId ?? throw new \LogicException('Request context has not started.');
    }

    /** @param array<string, scalar|null> $values */
    public function add(array $values): void
    {
        $this->values = [...$this->values, ...$values];
    }

    /** @return array<string, scalar|null> */
    public function values(): array
    {
        return $this->values;
    }

    public function clear(): void
    {
        $this->values = [];
    }
}
