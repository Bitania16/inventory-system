<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class OrderItem extends Model
{
    protected $fillable = [
        'order_id', 'product_variant_id', 'product_name_snapshot', 'sku_snapshot',
        'quantity_ordered', 'quantity_fulfilled', 'quantity_cancelled', 'unit_price',
        'discount_amount', 'tax_rate_percent', 'tax_amount', 'line_total',
    ];

    public function order()   { return $this->belongsTo(Order::class); }
    public function variant() { return $this->belongsTo(ProductVariant::class, 'product_variant_id'); }
}