<?php

namespace Database\Seeders;

use App\Models\PosRegister;
use App\Models\StockLocation;
use App\Models\Store;
use Illuminate\Database\Seeder;

class StoreSeeder extends Seeder
{
    public function run(): void
    {
        $store = Store::firstOrCreate(
            ['code' => 'MAIN'],
            ['name' => 'Main Store', 'is_active' => true]
        );

        StockLocation::firstOrCreate(
            ['store_id' => $store->id, 'code' => 'WH'],
            ['name' => 'Main Warehouse', 'location_type' => 'warehouse', 'is_sellable' => false]
        );

        StockLocation::firstOrCreate(
            ['store_id' => $store->id, 'code' => 'SF'],
            ['name' => 'Shop Floor', 'location_type' => 'shop_floor', 'is_sellable' => true]
        );

        PosRegister::firstOrCreate(
            ['store_id' => $store->id, 'code' => 'REG1'],
            ['name' => 'Register 1']
        );
    }
}