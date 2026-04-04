<?php

namespace App\Enums;

enum InvoiceKind: string
{
    case Opd = 'opd';
    case Lab = 'lab';
    case Procedure = 'procedure';
}
