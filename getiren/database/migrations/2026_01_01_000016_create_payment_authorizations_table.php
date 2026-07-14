<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('payment_authorizations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('order_id')->constrained()->cascadeOnDelete();

            $table->string('provider', 30);           // demo / iyzico / paytr …
            $table->string('provider_ref', 100);      // sağlayıcıdaki işlem kimliği
            $table->string('status', 20);

            $table->decimal('amount', 10, 2);                    // provizyona alınan
            $table->decimal('captured_amount', 10, 2)->nullable(); // fişe göre kesilen

            $table->timestamp('authorized_at')->nullable();
            $table->timestamp('captured_at')->nullable();
            $table->timestamp('voided_at')->nullable();

            $table->string('note', 255)->nullable();
            $table->json('meta')->nullable();
            $table->timestamps();

            $table->index(['order_id', 'status']);
            $table->unique(['provider', 'provider_ref']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('payment_authorizations');
    }
};
