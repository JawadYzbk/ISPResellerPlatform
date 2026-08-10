<?php

namespace App\Enums;

enum ServiceStatus: string
{
    case Pending = 'pending';
    case Active = 'active';
    case Suspended = 'suspended';
    case Paused = 'paused';
    case Terminated = 'terminated';

    public function label(): string
    {
        return ucfirst($this->value);
    }
}
