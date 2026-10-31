<?php

namespace Database\Seeders;

use App\Models\PaymentMethod;
use Illuminate\Database\Seeder;

class PaymentMethodSeeder extends Seeder
{
    public function run(): void
    {
        $methods = [
            ['code' => 'CASH',     'name' => 'Cash',          'method_type' => 'cash',          'requires_reference' => false, 'sort_order' => 1],
            ['code' => 'BANK',     'name' => 'Bank Transfer', 'method_type' => 'bank_transfer', 'requires_reference' => true,  'sort_order' => 2],
            ['code' => 'TELEBIRR', 'name' => 'Telebirr',      'method_type' => 'mobile_money',  'provider_name' => 'Ethio Telecom', 'requires_reference' => true, 'sort_order' => 3],
            ['code' => 'CARD',     'name' => 'Card',          'method_type' => 'card',          'requires_reference' => true,  'sort_order' => 4],
        ];

        foreach ($methods as $method) {
            PaymentMethod::firstOrCreate(['code' => $method['code']], $method);
        }
    }
}