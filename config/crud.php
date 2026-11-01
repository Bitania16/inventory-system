<?php

use App\Models\{Unit, TaxRate, PaymentMethod, Store, StockLocation, PosRegister, CustomerAddress, ProductImage};

return [
    'units' => [
        'model' => Unit::class,
        'filters' => [],
        'rules' => [
            'name' => 'required|string|max:80',
            'abbreviation' => 'required|string|max:20|unique:units,abbreviation,{id}',
            'allows_decimal_quantity' => 'boolean',
        ],
    ],
    'tax-rates' => [
        'model' => TaxRate::class,
        'filters' => ['is_active'],
        'rules' => [
            'code' => 'required|string|max:40|unique:tax_rates,code,{id}',
            'name' => 'required|string|max:120',
            'rate_percent' => 'required|numeric|min:0|max:100',
            'is_inclusive' => 'boolean',
            'is_active' => 'boolean',
            'effective_from' => 'nullable|date',
            'effective_to' => 'nullable|date',
        ],
    ],
    'payment-methods' => [
        'model' => PaymentMethod::class,
        'filters' => ['is_active', 'method_type'],
        'rules' => [
            'code' => 'required|string|max:40|unique:payment_methods,code,{id}',
            'name' => 'required|string|max:100',
            'method_type' => 'required|in:cash,bank_transfer,mobile_money,card,cheque,other',
            'provider_name' => 'nullable|string|max:100',
            'requires_reference' => 'boolean',
            'is_active' => 'boolean',
            'sort_order' => 'nullable|integer',
        ],
    ],
    'stores' => [
        'model' => Store::class,
        'filters' => ['is_active'],
        'rules' => [
            'code' => 'required|string|max:30|unique:stores,code,{id}',
            'name' => 'required|string|max:150',
            'phone' => 'nullable|string|max:30',
            'email' => 'nullable|email|max:255',
            'address' => 'nullable|string',
            'is_active' => 'boolean',
        ],
    ],
    'stock-locations' => [
        'model' => StockLocation::class,
        'filters' => ['store_id', 'location_type'],
        'rules' => [
            'store_id' => 'required|exists:stores,id',
            'code' => 'required|string|max:30',
            'name' => 'required|string|max:120',
            'location_type' => 'required|in:warehouse,shop_floor,returns,transit,damaged',
            'is_sellable' => 'boolean',
            'is_active' => 'boolean',
        ],
    ],
    'pos-registers' => [
        'model' => PosRegister::class,
        'filters' => ['store_id'],
        'rules' => [
            'store_id' => 'required|exists:stores,id',
            'code' => 'required|string|max:30',
            'name' => 'required|string|max:100',
            'is_active' => 'boolean',
        ],
    ],
    'customer-addresses' => [
        'model' => CustomerAddress::class,
        'filters' => ['customer_id'],
        'rules' => [
            'customer_id' => 'required|exists:customers,id',
            'label' => 'nullable|string|max:60',
            'address_type' => 'required|in:billing,shipping,both',
            'recipient_name' => 'nullable|string|max:180',
            'phone' => 'nullable|string|max:30',
            'country_code' => 'nullable|string|size:2',
            'region' => 'nullable|string|max:100',
            'city' => 'nullable|string|max:100',
            'sub_city' => 'nullable|string|max:100',
            'woreda' => 'nullable|string|max:100',
            'address_line' => 'nullable|string|max:255',
            'landmark' => 'nullable|string|max:255',
            'postal_code' => 'nullable|string|max:30',
            'is_default' => 'boolean',
        ],
    ],
    'product-images' => [
        'model' => ProductImage::class,
        'filters' => ['product_id'],
        'rules' => [
            'product_id' => 'required|exists:products,id',
            'image_path' => 'required|string|max:500',
            'alt_text' => 'nullable|string|max:255',
            'sort_order' => 'nullable|integer',
            'is_primary' => 'boolean',
        ],
    ],
];