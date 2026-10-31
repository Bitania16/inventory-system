<?php

namespace App\Models;

use App\Enums\StockTransferStatus;
use Illuminate\Database\Eloquent\Model;

class StockTransfer extends Model
{
    protected $fillable = [
        'transfer_number', 'from_stock_location_id', 'to_stock_location_id', 'status',
        'requested_by', 'approved_by', 'shipped_by', 'received_by',
        'requested_at', 'shipped_at', 'received_at', 'notes',
    ];

    protected $casts = [
        'status'       => StockTransferStatus::class,
        'requested_at' => 'datetime',
        'shipped_at'   => 'datetime',
        'received_at'  => 'datetime',
    ];

    public function fromLocation() { return $this->belongsTo(StockLocation::class, 'from_stock_location_id'); }
    public function toLocation()   { return $this->belongsTo(StockLocation::class, 'to_stock_location_id'); }
    public function requester()    { return $this->belongsTo(User::class, 'requested_by'); }
    public function approver()     { return $this->belongsTo(User::class, 'approved_by'); }
    public function shipper()      { return $this->belongsTo(User::class, 'shipped_by'); }
    public function receiver()     { return $this->belongsTo(User::class, 'received_by'); }
    public function items()        { return $this->hasMany(StockTransferItem::class); }
}