<?php

namespace App\Enums;

enum StockLocationType: string
{
    case Warehouse = 'warehouse';
    case ShopFloor = 'shop_floor';
    case Returns   = 'returns';
    case Transit   = 'transit';
    case Damaged   = 'damaged';
}