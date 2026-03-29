<?php

namespace App\Enums;

enum AppointmentStatus: string
{
    case Booked = 'booked';
    case Arrived = 'arrived';
    case UsedByWalkin = 'used_by_walkin';
    case Cancelled = 'cancelled';
}
