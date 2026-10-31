<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Unit extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'abbreviation',
        'allows_decimal_quantity',
    ];

    protected $casts = [
        'allows_decimal_quantity' => 'boolean',
    ];

    public function products()
    {
        return $this->hasMany(Product::class, 'base_unit_id');
    }
}