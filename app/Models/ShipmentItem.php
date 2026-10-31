<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ShipmentItem extends Model
{
    public $timestamps = false;

    protected $fillable = ['shipment_id', 'order_item_id', 'quantity_shipped'];

    public function shipment()  { return $this->belongsTo(Shipment::class); }
    public function orderItem() { return $this->belongsTo(OrderItem::class); }
}