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
        Schema::create('product_collection_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('collection_id')
                ->constrained('product_collections')
                ->cascadeOnDelete();
            $table->foreignId('product_id')
                ->constrained('products')
                ->cascadeOnDelete();
            $table->unsignedInteger('position')->default(0);
            $table->timestamps();

            $table->unique(['collection_id', 'product_id']);
            $table->index('position');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('product_collection_items');
    }
};
