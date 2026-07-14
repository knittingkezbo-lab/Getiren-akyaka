<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('audit_logs', function (Blueprint $table) {
            $table->id();

            // Aktör hesabı sonradan silinse bile kaydın "kim" sorusu cevaplanabilir kalsın diye ad kopyalanır
            $table->foreignId('actor_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('actor_name', 150);

            $table->string('action', 60);

            // Hedef (kurye / sipariş). Reddedilen kurye satırı silindiği için etiketi de kopyalanır.
            $table->string('subject_type')->nullable();
            $table->unsignedBigInteger('subject_id')->nullable();
            $table->string('subject_label', 150)->nullable();

            $table->string('description', 255);
            $table->json('meta')->nullable();
            $table->string('ip', 45)->nullable();

            // Yalnızca eklenir: updated_at yok
            $table->timestamp('created_at')->nullable();

            $table->index('action');
            $table->index('created_at');
            $table->index(['subject_type', 'subject_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('audit_logs');
    }
};
