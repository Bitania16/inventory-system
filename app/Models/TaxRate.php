<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class TaxRate extends Model
{
    protected $fillable = [
        'code', 'name', 'rate_percent', 'is_inclusive', 'is_active', 'effective_from', 'effective_to',
    ];

    protected $casts = [
        'is_inclusive' => 'boolean',
        'is_active'    => 'boolean',
    ];
}
