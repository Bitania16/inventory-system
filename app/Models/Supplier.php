<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Supplier extends Model
{
    use HasFactory;

    protected $fillable = [
    'supplier_code', 'name', 'contact_person', 'phone', 'email',
    'tax_number', 'address', 'payment_terms_days', 'is_active', 'notes',
];

protected $casts = ['is_active' => 'boolean'];
    public function products()
    {
        return $this->hasMany(Product::class);
    }
    public function purchaseOrders()
{
    return $this->hasMany(PurchaseOrder::class);
}
}