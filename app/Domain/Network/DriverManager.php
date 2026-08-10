<?php

namespace App\Domain\Network;

use App\Enums\ProvisioningMode;
use App\Models\Service;

final class DriverManager
{
    public function __construct(private ManualDriver $manual, private NullDriver $null, private ?FakeDriver $fake = null, private ?MikrotikApiDriver $mikrotik = null, private ?RadiusDriver $radius = null, private ?ExternalDriver $external = null) {}

    public function for(Service $service): NetworkDriver
    {
        if ($this->fake !== null) {
            return $this->fake;
        }

        return match ($service->provisioning_mode) {
            ProvisioningMode::Manual, ProvisioningMode::UpstreamCredential => $this->manual,
            ProvisioningMode::Mikrotik => $this->mikrotik ?? $this->null,
            ProvisioningMode::Radius => $this->radius ?? $this->null,
            ProvisioningMode::External => $this->external ?? $this->null,
        };
    }
}
