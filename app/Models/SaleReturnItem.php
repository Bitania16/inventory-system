<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SaleReturnItem extends Model
{
    public $timestamps = false;

    protected $fillable = [
        'sale_return_id', 'sale_item_id', 'product_variant_id',
        'quantity', 'condition', 'restock', 'refund_amount',
    ];

    protected $casts = ['restock' => 'boolean'];

    public function saleReturn() { return $this->belongsTo(SaleReturn::class); }
    public function saleItem()   { return $this->belongsTo(SaleItem::class); }
    public function variant()    { return $this->belongsTo(ProductVariant::class, 'product_variant_id'); }
}