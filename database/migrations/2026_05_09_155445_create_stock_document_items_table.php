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
        Schema::create('stock_document_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('document_id')->constrained('stock_documents')->onDelete('cascade');
            $table->foreignId('product_id')->constrained()->onDelete('cascade');
            $table->integer('quantity'); // Количество
            $table->decimal('price', 10, 2)->nullable(); // Цена за единицу (опционально)
            $table->text('comment')->nullable(); // Комментарий к позиции
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('stock_document_items');
    }
};
