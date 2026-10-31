<?php

namespace App\Models;

use App\Enums\ReturnStatus;
use Illuminate\Database\Eloquent\Model;

class SaleReturn extends Model
{
    protected $fillable = [
        'return_number', 'sale_id', 'customer_id', 'status', 'reason', 'requested_at',
        'received_at', 'processed_by', 'refund_total', 'restock_to_location_id',
    ];

    protected $casts = [
        'status'       => ReturnStatus::class,
        'requested_at' => 'datetime',
        'received_at'  => 'datetime',
    ];

    public function sale()      { return $this->belongsTo(Sale::class); }
    public function customer()  { return $this->belongsTo(Customer::class); }
    public function processor() { return $this->belongsTo(User::class, 'processed_by'); }
    public function restockLocation() { return $this->belongsTo(StockLocation::class, 'restock_to_location_id'); }
    public function items()     { return $this->hasMany(SaleReturnItem::class); }
    public function refunds()   { return $this->hasMany(SaleRefund::class); }
}