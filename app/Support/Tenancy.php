<?php

namespace App\Support;

use App\Models\Tenant;
use LogicException;

final class Tenancy
{
    private ?int $tenantId = null;

    private ?int $providerSettingsTenantId = null;

    public function set(Tenant|int $tenant): void
    {
        $model = $tenant instanceof Tenant ? $tenant : Tenant::query()->findOrFail($tenant);
        if ($this->providerSettingsTenantId !== null && $this->providerSettingsTenantId !== $model->getKey()) {
            app(TenantIntegrationSettings::class)->reset();
            $this->providerSettingsTenantId = null;
        }
        $this->tenantId = $model->getKey();
        setPermissionsTeamId($this->tenantId);
        if (is_array($model->provider_settings) && $model->provider_settings !== []) {
            app(TenantIntegrationSettings::class)->apply($model);
            $this->providerSettingsTenantId = $model->getKey();
        }
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
        setPermissionsTeamId(null);
        if ($this->providerSettingsTenantId !== null) {
            app(TenantIntegrationSettings::class)->reset();
            $this->providerSettingsTenantId = null;
        }
    }

    public function run(Tenant|int $tenant, callable $callback): mixed
    {
        $previous = $this->tenantId;
        $this->set($tenant);

        try {
            return $callback();
        } finally {
            if ($previous === null) {
                $this->clear();
            } else {
                $this->set($previous);
            }
        }
    }
}
