<?php

namespace App\Models;

use App\Enums\PaymentStatus;
use App\Enums\SaleStatus;
use Illuminate\Database\Eloquent\Model;

class Sale extends Model
{
    protected $fillable = [
        'sale_number', 'order_id', 'customer_id', 'store_id', 'stock_location_id',
        'cashier_shift_id', 'cashier_id', 'status', 'payment_status', 'currency_code',
        'subtotal', 'discount_total', 'tax_total', 'shipping_fee', 'grand_total',
        'credit_terms_days', 'due_date', 'issued_at', 'voided_at', 'notes',
    ];

    protected $casts = [
        'status'         => SaleStatus::class,
        'payment_status' => PaymentStatus::class,
        'due_date'       => 'date',
        'issued_at'      => 'datetime',
        'voided_at'      => 'datetime',
    ];

    public function order()       { return $this->belongsTo(Order::class); }
    public function customer()    { return $this->belongsTo(Customer::class); }
    public function store()       { return $this->belongsTo(Store::class); }
    public function shift()       { return $this->belongsTo(CashierShift::class, 'cashier_shift_id'); }
    public function cashier()     { return $this->belongsTo(User::class, 'cashier_id'); }
    public function items()       { return $this->hasMany(SaleItem::class); }
    public function allocations() { return $this->hasMany(PaymentAllocation::class); }
}