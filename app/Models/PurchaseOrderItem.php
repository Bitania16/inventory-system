<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PurchaseOrderItem extends Model
{
    protected $fillable = [
        'purchase_order_id', 'product_variant_id', 'quantity_ordered', 'quantity_received',
        'unit_cost', 'discount_amount', 'tax_amount', 'line_total',
    ];

    public function purchaseOrder() { return $this->belongsTo(PurchaseOrder::class); }
    public function variant()       { return $this->belongsTo(ProductVariant::class, 'product_variant_id'); }
}