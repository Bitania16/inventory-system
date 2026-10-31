<?php

namespace App\Models;

use App\Enums\CashierShiftStatus;
use Illuminate\Database\Eloquent\Model;

class CashierShift extends Model
{
    protected $fillable = [
        'pos_register_id', 'opened_by', 'closed_by', 'status', 'opening_cash',
        'counted_cash', 'expected_cash', 'variance_amount', 'opened_at', 'closed_at', 'notes',
    ];

    protected $casts = [
        'status'    => CashierShiftStatus::class,
        'opened_at' => 'datetime',
        'closed_at' => 'datetime',
    ];

    public function register() { return $this->belongsTo(PosRegister::class, 'pos_register_id'); }
    public function opener()   { return $this->belongsTo(User::class, 'opened_by'); }
    public function closer()   { return $this->belongsTo(User::class, 'closed_by'); }
    public function orders()   { return $this->hasMany(Order::class); }
public function cashMovements()
{
    return $this->hasMany(CashMovement::class);
}
    }