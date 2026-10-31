<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SaleItem extends Model
{
    protected $fillable = [
        'sale_id', 'order_item_id', 'product_variant_id', 'product_name_snapshot',
        'sku_snapshot', 'quantity', 'unit_price', 'discount_amount',
        'tax_rate_percent', 'tax_amount', 'line_total',
    ];

    public function sale()      { return $this->belongsTo(Sale::class); }
    public function orderItem() { return $this->belongsTo(OrderItem::class); }
    public function variant()   { return $this->belongsTo(ProductVariant::class, 'product_variant_id'); }
}