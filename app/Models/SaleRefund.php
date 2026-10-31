<?php

namespace App\Models;

use App\Enums\RefundStatus;
use Illuminate\Database\Eloquent\Model;

class SaleRefund extends Model
{
    protected $fillable = [
        'refund_number', 'sale_return_id', 'payment_method_id', 'amount', 'status',
        'transaction_reference', 'refunded_at', 'processed_by', 'notes',
    ];

    protected $casts = [
        'status'      => RefundStatus::class,
        'refunded_at' => 'datetime',
    ];

    public function saleReturn() { return $this->belongsTo(SaleReturn::class); }
    public function method()     { return $this->belongsTo(PaymentMethod::class, 'payment_method_id'); }
    public function processor()  { return $this->belongsTo(User::class, 'processed_by'); }
}