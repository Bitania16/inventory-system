<?php

namespace Database\Seeders;

use App\Models\Unit;
use Illuminate\Database\Seeder;

class UnitSeeder extends Seeder
{
    public function run(): void
    {
        $units = [
            ['name' => 'Piece',    'abbreviation' => 'pcs', 'allows_decimal_quantity' => false],
            ['name' => 'Kilogram', 'abbreviation' => 'kg',  'allows_decimal_quantity' => true],
            ['name' => 'Gram',     'abbreviation' => 'g',   'allows_decimal_quantity' => true],
            ['name' => 'Litre',    'abbreviation' => 'L',   'allows_decimal_quantity' => true],
            ['name' => 'Meter',    'abbreviation' => 'm',   'allows_decimal_quantity' => true],
            ['name' => 'Box',      'abbreviation' => 'box', 'allows_decimal_quantity' => false],
        ];

        foreach ($units as $unit) {
            Unit::firstOrCreate(['abbreviation' => $unit['abbreviation']], $unit);
        }
    }
}