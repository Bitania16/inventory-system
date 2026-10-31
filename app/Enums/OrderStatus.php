<?php

namespace App\Enums;

enum OrderStatus: string
{
    case Draft           = 'draft';
    case PendingPayment  = 'pending_payment';
    case Placed          = 'placed';
    case Confirmed       = 'confirmed';
    case Processing      = 'processing';
    case ReadyForPickup  = 'ready_for_pickup';
    case Shipped         = 'shipped';
    case Completed       = 'completed';
    case Cancelled       = 'cancelled';
    case Rejected        = 'rejected';
}