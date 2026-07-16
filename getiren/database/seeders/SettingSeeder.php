<?php

namespace Database\Seeders;

use App\Models\Setting;
use Illuminate\Database\Seeder;

class SettingSeeder extends Seeder
{
    public function run(): void
    {
        $defaults = [
            'safety_buffer_pct' => 15,     // %15 güvenlik payı (bilinen kalemler)
            'unknown_buffer_pct' => 35,    // tanınmayan kalem varsa payı buna yükselt (ek ödeme sürtünmesini azaltır)
            'fallback_item_price' => 60,   // sözlükte olmayan kalemin varsayılan birim fiyatı
            'min_order_total' => 100,      // asgari ürün tahmini (TL)
            'accepting_orders' => 1,       // sipariş alımı açık
            'auto_assign_courier' => 0,    // otomatik kurye ataması kapalı
        ];

        // Yalnızca YOKSA yaz — admin'in panelden ayarladığı değerleri deploy/seed ezmesin
        foreach ($defaults as $key => $value) {
            if (Setting::get($key) === null) {
                Setting::put($key, $value);
            }
        }
    }
}
