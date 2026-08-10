<?php

namespace App\Enums;

enum PaymentAttemptStatus: string
{
    case Pending = 'pending';
    case Succeeded = 'succeeded';
    case Failed = 'failed';
}
