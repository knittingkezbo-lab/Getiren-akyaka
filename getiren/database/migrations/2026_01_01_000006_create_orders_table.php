<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('orders', function (Blueprint $table) {
            $table->id();
            $table->string('code')->unique();                                  // A24-118
            $table->foreignId('customer_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('courier_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('zone_id')->constrained()->restrictOnDelete();

            $table->text('raw_text');                                          // serbest metin sipariş
            $table->string('address_label')->nullable();
            $table->text('address_text')->nullable();                          // sipariş anındaki adres kopyası
            $table->string('customer_note')->nullable();

            // Para alanları (bloke → tahsil → iade mantığı)
            $table->decimal('items_total', 12, 2)->default(0);                 // ürün tahmini
            $table->decimal('safety_buffer', 12, 2)->default(0);              // %15 güvenlik payı
            $table->decimal('service_fee', 12, 2)->default(0);                // teslimat bedeli
            $table->decimal('reserved_amount', 12, 2)->default(0);            // bloke edilen toplam
            $table->decimal('actual_receipt_amount', 12, 2)->nullable();      // gerçek fiş (ürünler)
            $table->decimal('captured_amount', 12, 2)->nullable();            // tahsil edilen
            $table->decimal('refund_amount', 12, 2)->nullable();              // iade edilen
            $table->decimal('extra_required_amount', 12, 2)->nullable();      // eksik/ek ödeme

            $table->string('status')->default('estimated')->index();          // App\Enums\OrderStatus
            $table->timestamp('reserved_at')->nullable();
            $table->timestamp('delivered_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('orders');
    }
};
