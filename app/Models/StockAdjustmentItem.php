<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class StockAdjustmentItem extends Model
{
    public $timestamps = false;

    protected $fillable = [
        'stock_adjustment_id', 'product_variant_id', 'system_quantity',
        'counted_quantity', 'difference_quantity', 'unit_cost', 'notes',
    ];

    public function adjustment() { return $this->belongsTo(StockAdjustment::class, 'stock_adjustment_id'); }
    public function variant()    { return $this->belongsTo(ProductVariant::class, 'product_variant_id'); }
}