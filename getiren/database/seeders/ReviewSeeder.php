<?php

namespace Database\Seeders;

use App\Enums\UserRole;
use App\Models\User;
use App\Models\Zone;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

/**
 * PayTR (veya başka) İNCELEME hesapları — istek üzerine çalışır, otomatik değil:
 *   php artisan db:seed --class=ReviewSeeder
 *
 * Amaç: sağlayıcı incelemesi için temiz, tek bir müşteri + kurye hesabı. Gerçek demo
 * verisi değil; inceleme bitince silinebilir. Şifre env'den okunur (depoya yazılmaz):
 *   REVIEW_EMAIL=inceleme@getirenakyaka.com
 *   REVIEW_PASSWORD=güçlü-bir-şifre
 *
 * DatabaseSeeder bunu ASLA otomatik çağırmaz — üretim müşteri tabanını kirletmez.
 */
class ReviewSeeder extends Seeder
{
    public function run(): void
    {
        $password = env('REVIEW_PASSWORD');

        if (blank($password)) {
            $this->command?->warn('ReviewSeeder atlandı: REVIEW_PASSWORD .env\'de tanımlı değil.');

            return;
        }

        $email = env('REVIEW_EMAIL', 'inceleme@getirenakyaka.com');
        [$local, $domain] = explode('@', $email, 2);
        $courierEmail = "{$local}+kurye@{$domain}";

        $customer = $this->makeUser('İnceleme Müşteri', $email, UserRole::Customer, $password);
        $this->makeUser('İnceleme Kurye', $courierEmail, UserRole::Courier, $password);

        // İnceleyene varsayılan adres — sipariş akışını hızlı denesin
        $zone = Zone::where('is_active', true)->orderBy('sort_order')->first();
        if ($zone) {
            $customer->addresses()->updateOrCreate(
                ['is_default' => true],
                ['label' => 'Ev', 'line' => 'Akyaka Merkez, Muğla', 'zone_id' => $zone->id],
            );
        }

        $this->command?->info('İnceleme hesapları hazır:');
        $this->command?->info("  müşteri: {$email}");
        $this->command?->info("  kurye  : {$courierEmail}  (onaylı)");
    }

    private function makeUser(string $name, string $email, UserRole $role, string $password): User
    {
        $user = User::updateOrCreate(
            ['email' => $email],
            ['name' => $name, 'password' => Hash::make($password), 'role' => $role],
        );

        // Fillable dışı alanlar: doğrulanmış başlasın; kurye ayrıca onaylı olsun
        $user->markEmailAsVerified();
        if ($role === UserRole::Courier) {
            $user->approve();
        }

        return $user;
    }
}
