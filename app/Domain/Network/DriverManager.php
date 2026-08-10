<?php

namespace App\Domain\Network;

use App\Enums\ProvisioningMode;
use App\Models\Service;

final class DriverManager
{
    public function __construct(private ManualDriver $manual, private NullDriver $null, private MikrotikApiDriver $mikrotik, private ?FakeDriver $fake = null) {}

    public function for(Service $service): NetworkDriver
    {
        if ($this->fake !== null) {
            return $this->fake;
        }

        return match ($service->provisioning_mode) {
            ProvisioningMode::Manual, ProvisioningMode::UpstreamCredential => $this->manual,
            ProvisioningMode::Mikrotik => $this->mikrotik,
            default => $this->null,
        };
    }
}
