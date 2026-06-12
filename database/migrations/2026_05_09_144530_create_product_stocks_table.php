<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('product_stocks', function (Blueprint $table) {
            $table->id();
            $table->foreignId('product_id')->constrained()->cascadeOnDelete();
            $table->foreignId('warehouse_id')->constrained()->cascadeOnDelete();
            $table->integer('quantity')->default(0)->comment('Доступное количество');
            $table->integer('reserved_quantity')->default(0)->comment('Зарезервированное количество');
            $table->timestamps();

            // Уникальный индекс: один товар может быть только один раз на одном складе
            $table->unique(['product_id', 'warehouse_id']);

            // Индексы для быстрого поиска
            $table->index('warehouse_id');
            $table->index('quantity');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('product_stocks');
    }
};
