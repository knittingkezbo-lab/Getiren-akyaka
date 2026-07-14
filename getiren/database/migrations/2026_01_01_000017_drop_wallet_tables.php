<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Schema;

/**
 * Sanal cüzdan kaldırıldı: hukuki görüş "kullanıcı bakiyesi tutma" diyor.
 * Ödeme artık sipariş başına provizyonla yapılıyor (payment_authorizations),
 * Getiren hiçbir noktada müşteri parası tutmuyor.
 *
 * Geri alınamaz — bakiye verisi bilinçli olarak taşınmıyor.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::dropIfExists('wallet_transactions'); // önce çocuk (wallet_id FK)
        Schema::dropIfExists('wallets');
    }

    public function down(): void
    {
        // Cüzdan bilinçli olarak terk edildi; geri getirmek için 000002 ve 000008 çalıştırılabilir.
    }
};
