<?php

namespace Database\Seeders;

use App\Models\PriceHint;
use Illuminate\Database\Seeder;

class PriceHintSeeder extends Seeder
{
    public function run(): void
    {
        $hints = [
            ['keyword' => 'süt', 'category' => 'Market', 'unit_price' => 50],
            ['keyword' => 'ekmek', 'category' => 'Fırın', 'unit_price' => 15],
            ['keyword' => 'su', 'category' => 'Market', 'unit_price' => 35],
            ['keyword' => 'kahve', 'category' => 'Market', 'unit_price' => 120],
            ['keyword' => 'yumurta', 'category' => 'Market', 'unit_price' => 90],
            ['keyword' => 'zeytinyağı', 'category' => 'Market', 'unit_price' => 220],
            ['keyword' => 'ağrı kesici', 'category' => 'Eczane', 'unit_price' => 150],
            ['keyword' => 'ilaç', 'category' => 'Eczane', 'unit_price' => 150],
            ['keyword' => 'gazete', 'category' => 'Büfe', 'unit_price' => 30],
        ];

        foreach ($hints as $hint) {
            PriceHint::updateOrCreate(['keyword' => $hint['keyword']], $hint);
        }
    }
}
