<?php

namespace App\Support;

use App\Models\Tenant;
use LogicException;

final class Tenancy
{
    private ?int $tenantId = null;

    public function set(Tenant|int $tenant): void
    {
        $this->tenantId = $tenant instanceof Tenant ? $tenant->getKey() : $tenant;
    }

    public function id(): ?int
    {
        return $this->tenantId;
    }

    public function requireId(): int
    {
        return $this->tenantId ?? throw new LogicException('A tenant context is required for this operation.');
    }

    public function clear(): void
    {
        $this->tenantId = null;
    }

    public function run(Tenant|int $tenant, callable $callback): mixed
    {
        $previous = $this->tenantId;
        $this->set($tenant);

        try {
            return $callback();
        } finally {
            $this->tenantId = $previous;
        }
    }
}
