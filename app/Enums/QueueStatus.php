<?php

namespace App\Enums;

enum QueueStatus: string
{
    case Active = 'active';
    case Closed = 'closed';
    case Finished = 'finished';
}
