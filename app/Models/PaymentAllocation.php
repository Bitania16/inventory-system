<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PaymentAllocation extends Model
{
    public $timestamps = false;

    protected $fillable = ['payment_id', 'sale_id', 'allocated_amount', 'allocated_at', 'allocated_by'];

    protected $casts = ['allocated_at' => 'datetime'];

    public function payment()   { return $this->belongsTo(Payment::class); }
    public function sale()      { return $this->belongsTo(Sale::class); }
    public function allocator() { return $this->belongsTo(User::class, 'allocated_by'); }
}