<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ProductVariant extends Model
{
    protected $fillable = [
    'product_id', 'sku', 'barcode', 'variant_name', 'attributes', 'cost_price',
    'selling_price', 'wholesale_price', 'minimum_selling_price', 'weight', 'is_active',
];

protected $casts = ['attributes' => 'array', 'is_active' => 'boolean'];

public function product() { return $this->belongsTo(Product::class); }

public function taxRates()
{
    return $this->belongsToMany(TaxRate::class, 'product_tax_rates');
}
    //
}
