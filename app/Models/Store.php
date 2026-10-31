<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Store extends Model
{
    protected $fillable = ['code', 'name', 'phone', 'email', 'address', 'is_active'];

    protected $casts = ['is_active' => 'boolean'];

    public function stockLocations()
    {
        return $this->hasMany(StockLocation::class);
    }

    public function posRegisters()
    {
        return $this->hasMany(PosRegister::class);
    }
}