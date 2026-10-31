<?php

namespace App\Models;

use App\Enums\StockLocationType;
use Illuminate\Database\Eloquent\Model;

class StockLocation extends Model
{
    protected $fillable = ['store_id', 'code', 'name', 'location_type', 'is_sellable', 'is_active'];

    protected $casts = [
        'location_type' => StockLocationType::class,
        'is_sellable'   => 'boolean',
        'is_active'     => 'boolean',
    ];

    public function store()
    {
        return $this->belongsTo(Store::class);
    }
}