<?php

namespace Database\Seeders;

use App\Models\Setting;
use Illuminate\Database\Seeder;

class SettingSeeder extends Seeder
{
    public function run(): void
    {
        $defaults = [
            'safety_buffer_pct' => 15,     // %15 güvenlik payı
            'min_order_total' => 100,      // asgari ürün tahmini (TL)
            'accepting_orders' => 1,       // sipariş alımı açık
            'auto_assign_courier' => 0,    // otomatik kurye ataması kapalı
        ];

        foreach ($defaults as $key => $value) {
            Setting::put($key, $value);
        }
    }
}
