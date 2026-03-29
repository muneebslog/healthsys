<?php

namespace App\Enums;

enum QueueTokenStatus: string
{
    case Reserved = 'reserved';
    case Waiting = 'waiting';
    case Serving = 'serving';
    case Done = 'done';
    case Skipped = 'skipped';
}
