<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class StockTransactionItem extends Model
{
    public $timestamps = false;

    protected $fillable = [
        'stock_transaction_id', 'stock_location_id', 'product_variant_id',
        'quantity_delta', 'unit_cost', 'notes',
    ];

    public function transaction()   { return $this->belongsTo(StockTransaction::class, 'stock_transaction_id'); }
    public function stockLocation() { return $this->belongsTo(StockLocation::class); }
    public function variant()       { return $this->belongsTo(ProductVariant::class, 'product_variant_id'); }
}