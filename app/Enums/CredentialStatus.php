<?php

namespace App\Enums;

enum CredentialStatus: string
{
    case Available = 'available';
    case Reserved = 'reserved';
    case Assigned = 'assigned';
    case Expired = 'expired';
    case Revoked = 'revoked';
}
