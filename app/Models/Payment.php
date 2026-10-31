<?php

namespace App\Models;

use App\Enums\PaymentRecordStatus;
use Illuminate\Database\Eloquent\Model;

class Payment extends Model
{
    protected $fillable = [
        'payment_number', 'customer_id', 'payment_method_id', 'amount', 'currency_code',
        'status', 'transaction_reference', 'provider_transaction_id', 'idempotency_key',
        'received_at', 'recorded_by', 'notes',
    ];

    protected $casts = [
        'status'      => PaymentRecordStatus::class,
        'received_at' => 'datetime',
    ];

    public function customer()    { return $this->belongsTo(Customer::class); }
    public function method()      { return $this->belongsTo(PaymentMethod::class, 'payment_method_id'); }
    public function recorder()    { return $this->belongsTo(User::class, 'recorded_by'); }
    public function allocations() { return $this->hasMany(PaymentAllocation::class); }
}