<?php

namespace App\Domain\Network;

use App\Enums\ProvisioningMode;
use App\Models\Service;

final class DriverManager
{
    public function __construct(
        private ManualDriver $manual,
        private MikrotikApiDriver $mikrotik,
        private RadiusDriver $radius,
        private ExternalDriver $external,
        private CredentialDriver $credential,
        private ?FakeDriver $fake = null,
    ) {}

    public function for(Service $service): NetworkDriver
    {
        if ($this->fake !== null) {
            return $this->fake;
        }

        return match ($service->provisioning_mode) {
            ProvisioningMode::Manual => $this->manual,
            ProvisioningMode::UpstreamCredential => $this->credential,
            ProvisioningMode::Mikrotik => $this->mikrotik,
            ProvisioningMode::Radius => $this->radius,
            ProvisioningMode::External => $this->external,
        };
    }
}
