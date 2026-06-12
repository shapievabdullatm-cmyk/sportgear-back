<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('shop_warehouse', function (Blueprint $table) {
            $table->id();
            $table->foreignId('shop_id')->constrained('shops')->cascadeOnDelete();
            $table->foreignId('warehouse_id')->constrained('warehouses')->cascadeOnDelete();
            $table->boolean('is_primary')->default(false);
            $table->integer('sort_order')->default(0);
            $table->timestamps();

            $table->unique(['shop_id', 'warehouse_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('shop_warehouse');
    }
};