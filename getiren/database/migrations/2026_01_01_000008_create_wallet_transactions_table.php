<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Cüzdan defteri (ledger): her para hareketi tek satır.
     * Bakiye bu satırlardan türetilebilir; wallets tablosundaki
     * balance/reserved bu defterin önbelleğidir. Çift-tahsil gibi
     * hatalar burada denetlenebilir (order_id + type ile idempotent).
     */
    public function up(): void
    {
        Schema::create('wallet_transactions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('wallet_id')->constrained()->cascadeOnDelete();
            $table->foreignId('order_id')->nullable()->constrained()->nullOnDelete();
            $table->string('type')->index();                          // App\Enums\TransactionType
            $table->decimal('amount', 12, 2)->default(0);             // kullanılabilir bakiye değişimi (işaretli)
            $table->decimal('reserved_delta', 12, 2)->default(0);    // bloke değişimi (işaretli)
            $table->decimal('balance_after', 12, 2)->default(0);     // işlem sonrası bakiye
            $table->decimal('reserved_after', 12, 2)->default(0);    // işlem sonrası bloke
            $table->string('note')->nullable();
            $table->json('meta')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('wallet_transactions');
    }
};
