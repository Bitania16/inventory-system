<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Customer extends Model
{
    protected $fillable = [
        'user_id', 'customer_code', 'customer_type', 'display_name', 'phone', 'email',
        'tax_number', 'credit_limit', 'default_credit_days', 'is_active', 'notes',
    ];

    protected $casts = ['is_active' => 'boolean'];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function addresses()
    {
        return $this->hasMany(CustomerAddress::class);
    }
}