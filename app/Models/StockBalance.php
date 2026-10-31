<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class StockBalance extends Model
{
    const CREATED_AT = null; // table has only updated_at

    protected $fillable = [
        'stock_location_id', 'product_variant_id',
        'quantity_on_hand', 'quantity_reserved', 'reorder_level',
    ];

    public function stockLocation() { return $this->belongsTo(StockLocation::class); }
    public function variant()       { return $this->belongsTo(ProductVariant::class, 'product_variant_id'); }

    // Stock you can still sell
    public function getAvailableAttribute()
    {
        return $this->quantity_on_hand - $this->quantity_reserved;
    }
}