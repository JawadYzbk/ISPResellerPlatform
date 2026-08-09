<?php

namespace App\Enums;

enum NetworkState: string
{
    case Unknown = 'unknown';
    case PendingSync = 'pending_sync';
    case InSync = 'in_sync';
    case Drifted = 'drifted';
    case Failed = 'failed';
}
