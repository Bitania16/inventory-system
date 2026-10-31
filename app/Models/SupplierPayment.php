<?php

namespace App\Models;

use App\Enums\PaymentRecordStatus;
use Illuminate\Database\Eloquent\Model;

class SupplierPayment extends Model
{
    protected $fillable = [
        'supplier_id', 'purchase_order_id', 'payment_method_id', 'payment_number', 'amount',
        'currency_code', 'status', 'transaction_reference', 'paid_at', 'recorded_by', 'notes',
    ];

    protected $casts = [
        'status'  => PaymentRecordStatus::class,
        'paid_at' => 'datetime',
    ];

    public function supplier()      { return $this->belongsTo(Supplier::class); }
    public function purchaseOrder() { return $this->belongsTo(PurchaseOrder::class); }
    public function method()        { return $this->belongsTo(PaymentMethod::class, 'payment_method_id'); }
    public function recorder()      { return $this->belongsTo(User::class, 'recorded_by'); }
}