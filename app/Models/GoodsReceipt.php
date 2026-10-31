<?php

namespace App\Models;

use App\Enums\GoodsReceiptStatus;
use Illuminate\Database\Eloquent\Model;

class GoodsReceipt extends Model
{
    protected $fillable = [
        'receipt_number', 'purchase_order_id', 'supplier_id', 'stock_location_id', 'status',
        'supplier_delivery_note', 'received_at', 'received_by', 'notes',
    ];

    protected $casts = [
        'status'      => GoodsReceiptStatus::class,
        'received_at' => 'datetime',
    ];

    public function purchaseOrder() { return $this->belongsTo(PurchaseOrder::class); }
    public function supplier()      { return $this->belongsTo(Supplier::class); }
    public function stockLocation() { return $this->belongsTo(StockLocation::class); }
    public function receiver()      { return $this->belongsTo(User::class, 'received_by'); }
    public function items()         { return $this->hasMany(GoodsReceiptItem::class); }
}