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
        Schema::create('stock_movements', function (Blueprint $table) {
            $table->id();
            $table->foreignId('product_id')->constrained()->cascadeOnDelete();
            $table->foreignId('warehouse_id')->constrained()->cascadeOnDelete();
            $table->enum('type', ['in', 'out', 'transfer_in', 'transfer_out', 'adjustment'])->comment('Тип движения: приход, расход, перемещение, корректировка');
            $table->integer('quantity')->comment('Количество (положительное или отрицательное)');
            $table->integer('quantity_before')->comment('Остаток до операции');
            $table->integer('quantity_after')->comment('Остаток после операции');
            $table->foreignId('related_warehouse_id')->nullable()->constrained('warehouses')->nullOnDelete()->comment('Связанный склад (для перемещений)');
            $table->string('reason')->nullable()->comment('Причина движения');
            $table->text('comment')->nullable()->comment('Комментарий');
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete()->comment('Кто выполнил операцию');
            $table->timestamps();

            // Индексы для быстрого поиска
            $table->index('product_id');
            $table->index('warehouse_id');
            $table->index('type');
            $table->index('created_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('stock_movements');
    }
};
