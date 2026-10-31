<?php

namespace App\Enums;

enum ReturnStatus: string
{
    case Requested = 'requested';
    case Approved  = 'approved';
    case Rejected  = 'rejected';
    case Received  = 'received';
    case Refunded  = 'refunded';
    case Completed = 'completed';
    case Cancelled = 'cancelled';
}