<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Katmanlı fiyat kaynağı: gözlenen (gerçek fiş/elle giriş) > manuel (admin) >
 * referans (dış kaynak, iç kullanım) > varsayılan.
 *
 * unit_price her zaman "geçerli" fiyattır; source hangi katmandan geldiğini söyler.
 * Böylece gözlenen yerel fiyat, referans/varsayılanı ezer ve zamanla gerçeğe yakınsar.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('price_hints', function (Blueprint $table) {
            $table->string('source', 20)->default('manual')->after('unit_price');
            $table->unsignedInteger('observed_count')->default(0)->after('source');
            $table->timestamp('last_observed_at')->nullable()->after('observed_count');
            $table->decimal('reference_price', 10, 2)->nullable()->after('last_observed_at');
            $table->timestamp('reference_updated_at')->nullable()->after('reference_price');

            $table->index('source');
            $table->index('last_observed_at');
        });
    }

    public function down(): void
    {
        Schema::table('price_hints', function (Blueprint $table) {
            $table->dropIndex(['source']);
            $table->dropIndex(['last_observed_at']);
            $table->dropColumn(['source', 'observed_count', 'last_observed_at', 'reference_price', 'reference_updated_at']);
        });
    }
};
