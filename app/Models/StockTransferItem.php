<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class StockTransferItem extends Model
{
    protected $fillable = [
        'stock_transfer_id', 'product_variant_id',
        'quantity_requested', 'quantity_shipped', 'quantity_received',
    ];

    public function transfer() { return $this->belongsTo(StockTransfer::class, 'stock_transfer_id'); }
    public function variant()  { return $this->belongsTo(ProductVariant::class, 'product_variant_id'); }
}