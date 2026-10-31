<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PosRegister extends Model
{
    protected $fillable = ['store_id', 'code', 'name', 'is_active'];

    protected $casts = ['is_active' => 'boolean'];

    public function store()
    {
        return $this->belongsTo(Store::class);
    }
}