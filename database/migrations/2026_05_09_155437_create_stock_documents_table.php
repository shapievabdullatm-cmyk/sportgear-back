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
        Schema::create('stock_documents', function (Blueprint $table) {
            $table->id();
            $table->string('number')->unique(); // Номер документа
            $table->enum('type', ['in', 'out', 'transfer']); // Тип: приход, расход, перемещение
            $table->foreignId('warehouse_id')->constrained()->onDelete('cascade'); // Склад
            $table->foreignId('to_warehouse_id')->nullable()->constrained('warehouses')->onDelete('cascade'); // Целевой склад для перемещения
            $table->enum('status', ['draft', 'completed', 'cancelled'])->default('draft'); // Статус
            $table->timestamp('completed_at')->nullable(); // Дата проведения
            $table->foreignId('user_id')->nullable()->constrained()->onDelete('set null'); // Кто создал
            $table->foreignId('completed_by')->nullable()->constrained('users')->onDelete('set null'); // Кто провел
            $table->text('comment')->nullable(); // Комментарий
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('stock_documents');
    }
};
