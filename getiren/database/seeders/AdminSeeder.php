<?php

namespace Database\Seeders;

use App\Enums\UserRole;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

/**
 * Üretim için TEK gerçek yönetici hesabı. Bilgiler env'den okunur — depoya
 * gerçek şifre yazılmaz. Anahtarlar boşsa hiçbir şey yapmaz (uyarır).
 *
 *   ADMIN_NAME="Ad Soyad"
 *   ADMIN_EMAIL=yonetici@getirenakyaka.com
 *   ADMIN_PASSWORD=güçlü-bir-şifre
 */
class AdminSeeder extends Seeder
{
    public function run(): void
    {
        $email = env('ADMIN_EMAIL');
        $password = env('ADMIN_PASSWORD');

        if (blank($email) || blank($password)) {
            $this->command?->warn('AdminSeeder atlandı: ADMIN_EMAIL / ADMIN_PASSWORD .env\'de tanımlı değil.');

            return;
        }

        $admin = User::updateOrCreate(
            ['email' => $email],
            [
                'name' => env('ADMIN_NAME', 'Yönetici'),
                'password' => Hash::make($password),
                'role' => UserRole::Admin,
            ],
        );

        // Fillable dışı alanlar (forceFill): doğrulanmış + onaylı başlasın
        $admin->markEmailAsVerified();
        $admin->approve();

        $this->command?->info("Yönetici hazır: {$email}");
    }
}
