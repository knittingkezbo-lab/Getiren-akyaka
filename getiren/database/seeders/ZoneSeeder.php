<?php

namespace Database\Seeders;

use App\Models\Zone;
use Illuminate\Database\Seeder;

class ZoneSeeder extends Seeder
{
    public function run(): void
    {
        $zones = [
            ['key' => 'akyaka', 'name' => 'Akyaka Merkez', 'service_fee' => 250, 'is_active' => true, 'sort_order' => 1],
            ['key' => 'gokova', 'name' => 'Gökova', 'service_fee' => 350, 'is_active' => true, 'sort_order' => 2],
            ['key' => 'akcapinar', 'name' => 'Akçapınar', 'service_fee' => 350, 'is_active' => true, 'sort_order' => 3],
            ['key' => 'atakoy', 'name' => 'Ataköy', 'service_fee' => 400, 'is_active' => false, 'sort_order' => 4],
        ];

        foreach ($zones as $zone) {
            Zone::updateOrCreate(['key' => $zone['key']], $zone);
        }
    }
}
