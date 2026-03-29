<?php

namespace App\Enums;

enum UserRole: string
{
    case Staff = 'staff';
    case Admin = 'admin';
    case Owner = 'owner';
    case Doctor = 'doctor';
    case FinanceManager = 'finance_manager';
}
