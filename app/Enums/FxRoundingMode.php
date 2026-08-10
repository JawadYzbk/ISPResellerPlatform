<?php

namespace App\Enums;

enum FxRoundingMode: string
{
    case HalfUp = 'half_up';
    case Floor = 'floor';
    case Ceil = 'ceil';
}
