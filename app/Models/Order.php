<?php

namespace App\Models;

use App\Enums\OrderStatus;
use Illuminate\Database\Eloquent\Model;

class Order extends Model
{
    protected $fillable = [
        'order_number', 'source', 'status', 'fulfillment_type', 'customer_id', 'store_id',
        'stock_location_id', 'pos_register_id', 'cashier_shift_id', 'created_by',
        'billing_address_id', 'shipping_address_id', 'billing_address_snapshot',
        'shipping_address_snapshot', 'currency_code', 'subtotal', 'discount_total',
        'tax_total', 'shipping_fee', 'grand_total', 'customer_notes', 'internal_notes',
        'placed_at', 'confirmed_at', 'cancelled_at',
    ];

    protected $casts = [
        'status'       => OrderStatus::class,
        'placed_at'    => 'datetime',
        'confirmed_at' => 'datetime',
        'cancelled_at' => 'datetime',
    ];

    public function customer()      { return $this->belongsTo(Customer::class); }
    public function store()         { return $this->belongsTo(Store::class); }
    public function stockLocation() { return $this->belongsTo(StockLocation::class); }
    public function register()      { return $this->belongsTo(PosRegister::class, 'pos_register_id'); }
    public function shift()         { return $this->belongsTo(CashierShift::class, 'cashier_shift_id'); }
    public function creator()       { return $this->belongsTo(User::class, 'created_by'); }
    public function items()         { return $this->hasMany(OrderItem::class); }
    public function shipments()     { return $this->hasMany(Shipment::class); }
}