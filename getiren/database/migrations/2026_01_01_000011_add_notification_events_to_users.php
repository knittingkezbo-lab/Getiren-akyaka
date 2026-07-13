<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            // Olay-bazlı tercih haritası: {assigned:bool, on_the_way:bool, ...}
            // null = tüm olaylar açık (varsayılan)
            $table->json('notification_events')->nullable()->after('notify_web');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('notification_events');
        });
    }
};
