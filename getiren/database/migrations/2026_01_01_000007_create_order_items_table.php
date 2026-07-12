<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('order_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('order_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->unsignedInteger('qty')->default(1);
            $table->decimal('estimated_price', 10, 2)->default(0);   // tahmini
            $table->decimal('actual_price', 10, 2)->nullable();      // kuryenin girdiği gerçek fiyat
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('order_items');
    }
};
