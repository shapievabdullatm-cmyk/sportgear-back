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
            $table->foreignId('product_id')->nullable()->constrained('products')->nullOnDelete();

            // Snapshot товара — чтобы изменение/удаление продукта не ломало историю
            $table->string('product_title');
            $table->string('product_slug')->nullable();
            $table->string('product_image', 500)->nullable();
            $table->string('product_size', 100)->nullable();

            $table->decimal('price', 12, 2);
            $table->integer('quantity');
            $table->decimal('total', 12, 2);

            // Откуда зарезервировано/будет списано. Админ может переназначить
            // на этапе сборки. Nullable — если склад удалили, резерв уже снят.
            $table->foreignId('reserved_warehouse_id')->nullable()
                ->constrained('warehouses')->nullOnDelete();

            $table->timestamps();

            $table->index('order_id');
            $table->index('product_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('order_items');
    }
};