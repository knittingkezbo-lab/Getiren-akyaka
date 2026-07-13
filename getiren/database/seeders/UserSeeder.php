<?php

namespace Database\Seeders;

use App\Enums\TransactionType;
use App\Enums\UserRole;
use App\Models\Address;
use App\Models\User;
use App\Models\Wallet;
use App\Models\Zone;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class UserSeeder extends Seeder
{
    public function run(): void
    {
        // Yönetici
        $this->makeUser('Ayşe Yılmaz', 'admin@getiren.test', UserRole::Admin, '+90 555 000 0001');

        // Kuryeler
        $couriers = [
            ['Mert Kaya', 'mert@getiren.test', '+90 555 100 0001'],
            ['Deniz Yıldız', 'deniz@getiren.test', '+90 555 100 0002'],
            ['Selim Barış', 'selim@getiren.test', '+90 555 100 0003'],
            ['Ece Koç', 'ece@getiren.test', '+90 555 100 0004'],
            ['Tuna Gül', 'tuna@getiren.test', '+90 555 100 0005'],
        ];
        foreach ($couriers as [$name, $email, $phone]) {
            $this->makeUser($name, $email, UserRole::Courier, $phone);
        }

        // Müşteriler: [ad, e-posta, telefon, açılış bakiyesi, bölge anahtarı]
        $customers = [
            ['Gencer Ger', 'gencer@bizsim.com', '+90 555 111 2233', 2000, 'akyaka'],
            ['Selin Ak', 'selin@example.com', '+90 555 222 3344', 1000, 'gokova'],
            ['Barış Tan', 'baris@example.com', '+90 555 333 4455', 1000, 'akcapinar'],
            ['Elif Demir', 'elif@example.com', '+90 555 444 5566', 1000, 'akyaka'],
            ['Can Polat', 'can@example.com', '+90 555 555 6677', 1000, 'gokova'],
        ];
        foreach ($customers as [$name, $email, $phone, $topup, $zoneKey]) {
            $user = $this->makeUser($name, $email, UserRole::Customer, $phone);
            $this->setupCustomer($user, (float) $topup, $zoneKey);
        }
    }

    private function makeUser(string $name, string $email, UserRole $role, string $phone): User
    {
        $user = User::updateOrCreate(
            ['email' => $email],
            [
                'name' => $name,
                'password' => Hash::make('password'),
                'role' => $role,
                'phone' => $phone,
                'email_verified_at' => now(),
            ],
        );

        // Seed hesapları onaylı başlar (yalnızca yeni kurye kayıtları pending)
        if (! $user->isApproved()) {
            $user->approve();
        }

        return $user;
    }

    private function setupCustomer(User $user, float $topup, string $zoneKey): void
    {
        $wallet = Wallet::updateOrCreate(
            ['user_id' => $user->id],
            ['balance' => 0, 'reserved' => 0, 'currency' => 'TRY'],
        );

        // Açılış bakiyesi — deftere yükleme olarak yazılır (yalnızca ilk kez)
        if ($topup > 0 && $wallet->transactions()->doesntExist()) {
            $wallet->recordTransaction(TransactionType::TopUp, $topup, 0, null, 'Açılış bakiyesi');
        }

        $zone = Zone::where('key', $zoneKey)->first();
        Address::updateOrCreate(
            ['user_id' => $user->id, 'label' => 'Ev'],
            [
                'zone_id' => $zone?->id,
                'line' => 'Şirinyer Mah. Deniz Sk. No:'.random_int(1, 40),
                'door_note' => 'Zil çalışmıyor, arayın',
                'is_default' => true,
            ],
        );
    }
}
