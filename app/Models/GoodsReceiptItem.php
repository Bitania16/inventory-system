<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class GoodsReceiptItem extends Model
{
    public $timestamps = false;

    protected $fillable = [
        'goods_receipt_id', 'purchase_order_item_id', 'product_variant_id', 'quantity_received', 'unit_cost',
    ];

    public function goodsReceipt()      { return $this->belongsTo(GoodsReceipt::class); }
    public function purchaseOrderItem() { return $this->belongsTo(PurchaseOrderItem::class); }
    public function variant()           { return $this->belongsTo(ProductVariant::class, 'product_variant_id'); }
}