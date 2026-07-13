<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            // İade/çekim için banka bilgisi (boşlukları ayıklanmış TR IBAN + hesap sahibi)
            $table->string('iban', 34)->nullable()->after('phone');
            $table->string('iban_holder', 150)->nullable()->after('iban');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn(['iban', 'iban_holder']);
        });
    }
};
