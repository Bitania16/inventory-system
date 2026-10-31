<?php

namespace App\Enums;

enum SaleStatus: string
{
    case Posted            = 'posted';
    case Voided            = 'voided';
    case PartiallyReturned = 'partially_returned';
    case Returned          = 'returned';
}