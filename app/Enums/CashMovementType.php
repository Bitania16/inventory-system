<?php

namespace App\Enums;

enum CashMovementType: string
{
    case CashIn            = 'cash_in';
    case CashOut           = 'cash_out';
    case OpeningFloat      = 'opening_float';
    case ClosingAdjustment = 'closing_adjustment';
}