<?php

namespace App\Enums;

enum BillingCadence: string
{
    case Monthly = 'monthly';
    case Weekly = 'weekly';
    case Custom = 'custom';
}
