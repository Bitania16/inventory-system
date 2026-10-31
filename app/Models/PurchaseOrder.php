<?php

namespace App\Models;

use App\Enums\PurchaseOrderStatus;
use Illuminate\Database\Eloquent\Model;

class PurchaseOrder extends Model
{
    protected $fillable = [
        'purchase_order_number', 'supplier_id', 'store_id', 'destination_stock_location_id',
        'status', 'currency_code', 'ordered_at', 'expected_at', 'subtotal', 'discount_total',
        'tax_total', 'grand_total', 'created_by', 'approved_by', 'notes',
    ];

    protected $casts = [
        'status'      => PurchaseOrderStatus::class,
        'ordered_at'  => 'datetime',
        'expected_at' => 'datetime',
    ];

    public function supplier()            { return $this->belongsTo(Supplier::class); }
    public function store()               { return $this->belongsTo(Store::class); }
    public function destinationLocation() { return $this->belongsTo(StockLocation::class, 'destination_stock_location_id'); }
    public function creator()             { return $this->belongsTo(User::class, 'created_by'); }
    public function approver()            { return $this->belongsTo(User::class, 'approved_by'); }
    public function items()               { return $this->hasMany(PurchaseOrderItem::class); }
    public function goodsReceipts()       { return $this->hasMany(GoodsReceipt::class); }
}