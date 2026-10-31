<?php

namespace App\Models;

use App\Enums\PaymentMethodType;
use Illuminate\Database\Eloquent\Model;

class PaymentMethod extends Model
{
    protected $fillable = [
        'code', 'name', 'method_type', 'provider_name', 'requires_reference', 'is_active', 'sort_order',
    ];

    protected $casts = [
        'method_type'        => PaymentMethodType::class,
        'requires_reference' => 'boolean',
        'is_active'          => 'boolean',
    ];
}