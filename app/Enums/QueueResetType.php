<?php

namespace App\Enums;

enum QueueResetType: string
{
    case PerShift = 'per_shift';
    case Daily = 'daily';
}
