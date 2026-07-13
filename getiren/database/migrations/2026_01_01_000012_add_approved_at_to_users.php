<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            // Kurye hesap onayı: NULL = onay bekliyor, dolu = onaylı.
            $table->timestamp('approved_at')->nullable()->after('email_verified_at');
        });

        // Mevcut hesaplar geriye dönük onaylı sayılır (yalnızca yeni kurye kayıtları pending başlar)
        DB::table('users')->update(['approved_at' => now()]);
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('approved_at');
        });
    }
};
