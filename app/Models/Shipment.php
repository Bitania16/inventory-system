<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Shipment extends Model
{
    protected $fillable = [
        'shipment_number', 'order_id', 'status', 'shipping_provider', 'tracking_number',
        'recipient_name', 'recipient_phone', 'delivery_address_snapshot',
        'shipped_at', 'delivered_at', 'handled_by', 'notes',
    ];

    protected $casts = ['shipped_at' => 'datetime', 'delivered_at' => 'datetime'];

    public function order()   { return $this->belongsTo(Order::class); }
    public function handler() { return $this->belongsTo(User::class, 'handled_by'); }
    public function items()   { return $this->hasMany(ShipmentItem::class); }
}