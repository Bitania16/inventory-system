<?php

namespace App\Models;

use App\Enums\StockAdjustmentStatus;
use Illuminate\Database\Eloquent\Model;

class StockAdjustment extends Model
{
    protected $fillable = [
        'adjustment_number', 'stock_location_id', 'status', 'reason',
        'created_by', 'approved_by', 'posted_by', 'counted_at', 'posted_at', 'notes',
    ];

    protected $casts = [
        'status'     => StockAdjustmentStatus::class,
        'counted_at' => 'datetime',
        'posted_at'  => 'datetime',
    ];

    public function stockLocation() { return $this->belongsTo(StockLocation::class); }
    public function creator()       { return $this->belongsTo(User::class, 'created_by'); }
    public function approver()      { return $this->belongsTo(User::class, 'approved_by'); }
    public function poster()        { return $this->belongsTo(User::class, 'posted_by'); }
    public function items()         { return $this->hasMany(StockAdjustmentItem::class); }
}