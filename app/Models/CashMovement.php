<?php

namespace App\Models;

use App\Enums\CashMovementType;
use Illuminate\Database\Eloquent\Model;

class CashMovement extends Model
{
    public $timestamps = false;

    protected $fillable = [
        'cashier_shift_id', 'movement_type', 'amount', 'reason',
        'payment_id', 'recorded_by', 'occurred_at',
    ];

    protected $casts = [
        'movement_type' => CashMovementType::class,
        'occurred_at'   => 'datetime',
    ];

    public function shift()    { return $this->belongsTo(CashierShift::class, 'cashier_shift_id'); }
    public function payment()  { return $this->belongsTo(Payment::class); }
    public function recorder() { return $this->belongsTo(User::class, 'recorded_by'); }
}