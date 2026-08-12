<?php

namespace App\Enums;

enum CredentialStatus: string
{
    case Imported = 'imported';
    case Available = 'available';
    case Reserved = 'reserved';
    case Assigned = 'assigned';
    case Active = 'active';
    case Expired = 'expired';
    case Revoked = 'revoked';
    case Invalid = 'invalid';
}
