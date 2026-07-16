<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * "Otomatik kurye ataması" anahtarı hiçbir yerde okunmuyordu: yönetici açsa da
 * kapatsa da hiçbir şey değişmiyordu. Yalan söyleyen bir anahtar, olmayandan
 * kötüdür — ayarı kaldırıyoruz. Gerçekten gerekirse davranışıyla birlikte gelir.
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::table('settings')->where('key', 'auto_assign_courier')->delete();
    }

    public function down(): void
    {
        // Geri alınacak bir davranış yok; ayar yeniden eklenirse varsayılanı seeder verir.
    }
};
