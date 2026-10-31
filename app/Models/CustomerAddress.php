<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class CustomerAddress extends Model
{
    protected $fillable = [
        'customer_id', 'label', 'address_type', 'recipient_name', 'phone', 'country_code',
        'region', 'city', 'sub_city', 'woreda', 'address_line', 'landmark', 'postal_code', 'is_default',
    ];

    protected $casts = ['is_default' => 'boolean'];

    public function customer()
    {
        return $this->belongsTo(Customer::class);
    }
}