<?php

namespace App\Enums;

enum IncidentStatus: string
{
    case Open = 'open';
    case Acknowledged = 'acknowledged';
    case Resolved = 'resolved';
}
