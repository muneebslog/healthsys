<?php

namespace App\Enums;

enum VisitStatus: string
{
    case Pending = 'pending';
    case InProgress = 'in_progress';
    case Done = 'done';
}
