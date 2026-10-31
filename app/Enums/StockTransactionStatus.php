<?php

namespace App\Enums;

enum StockTransactionStatus: string
{
    case Draft    = 'draft';
    case Posted   = 'posted';
    case Reversed = 'reversed';
}