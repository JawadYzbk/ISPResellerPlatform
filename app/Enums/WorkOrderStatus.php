<?php

namespace App\Enums;

enum WorkOrderStatus: string
{
    case Pending = 'pending';
    case Assigned = 'assigned';
    case InProgress = 'in_progress';
    case Completed = 'completed';
    case Failed = 'failed';
    case Cancelled = 'cancelled';
}
