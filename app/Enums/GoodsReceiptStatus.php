<?php

namespace App\Enums;

enum GoodsReceiptStatus: string
{
    case Draft     = 'draft';
    case Posted    = 'posted';
    case Cancelled = 'cancelled';
}