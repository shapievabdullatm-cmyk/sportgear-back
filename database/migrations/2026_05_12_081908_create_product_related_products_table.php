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
        Schema::create('product_related_products', function (Blueprint $table) {
            $table->id();
            $table->foreignId('product_id')->constrained('products')->cascadeOnDelete();
            $table->foreignId('related_product_id')->constrained('products')->cascadeOnDelete();
            $table->integer('sort')->default(0);
            $table->timestamps();

            // Уникальность пары
            $table->unique(['product_id', 'related_product_id']);

            // Индексы для производительности
            $table->index('product_id');
            $table->index('related_product_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('product_related_products');
    }
};
