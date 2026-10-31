<?php

namespace App\Enums;

enum StockTransferStatus: string
{
    case Draft             = 'draft';
    case Requested         = 'requested';
    case Approved          = 'approved';
    case InTransit         = 'in_transit';
    case PartiallyReceived = 'partially_received';
    case Received          = 'received';
    case Cancelled         = 'cancelled';
}