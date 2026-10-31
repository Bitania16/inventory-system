<?php

namespace App\Models;

use App\Enums\ReservationStatus;
use Illuminate\Database\Eloquent\Model;

class StockReservation extends Model
{
    protected $fillable = [
        'order_item_id', 'stock_location_id', 'product_variant_id', 'quantity',
        'status', 'expires_at', 'released_at', 'consumed_at', 'created_by',
    ];

    protected $casts = [
        'status'      => ReservationStatus::class,
        'expires_at'  => 'datetime',
        'released_at' => 'datetime',
        'consumed_at' => 'datetime',
    ];

    public function orderItem()     { return $this->belongsTo(OrderItem::class); }
    public function stockLocation() { return $this->belongsTo(StockLocation::class); }
    public function variant()       { return $this->belongsTo(ProductVariant::class, 'product_variant_id'); }
    public function creator()       { return $this->belongsTo(User::class, 'created_by'); }
}