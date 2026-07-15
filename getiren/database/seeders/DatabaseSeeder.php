<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        // Her ortamda gereken gerçek çekirdek veri (bölgeler, ayarlar, fiyat sözlüğü)
        $this->call([
            ZoneSeeder::class,
            SettingSeeder::class,
            PriceHintSeeder::class,
        ]);

        // Üretimde yalnızca gerçek admin; dev/demo verisi asla üretime sızmaz
        if (app()->environment('production')) {
            $this->call(AdminSeeder::class);
        } else {
            $this->call([
                UserSeeder::class,
                DemoOrderSeeder::class,
            ]);
        }
    }
}
