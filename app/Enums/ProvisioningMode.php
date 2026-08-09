<?php

namespace App\Enums;

enum ProvisioningMode: string
{
    case Manual = 'manual';
    case UpstreamCredential = 'upstream_credential';
    case Mikrotik = 'mikrotik';
    case Radius = 'radius';
    case External = 'external';
}
