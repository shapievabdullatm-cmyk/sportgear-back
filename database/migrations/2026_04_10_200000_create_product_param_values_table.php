<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Одна таблица на все типы значений.
     * В зависимости от filter_type параметра заполняется нужное поле:
     *
     *  STRING, TEXT           → value_string / value_text
     *  INTEGER                → value_int
     *  FLOAT                  → value_float
     *  UNIT                   → value_float + unit (берётся из params.unit)
     *  RANGE                  → value_min + value_max
     *  SELECT                 → param_option_id
     *  MULTISELECT, COLOR     → несколько строк с param_option_id (по одной на значение)
     *  BOOLEAN                → value_boolean
     */
    public function up(): void
    {
        Schema::create('product_param_values', function (Blueprint $table) {
            $table->id();
            $table->foreignId('product_id')
                ->constrained('products')
                ->cascadeOnDelete();
            $table->foreignId('param_id')
                ->constrained('params')
                ->cascadeOnDelete();

            // Ссылка на опцию (SELECT / MULTISELECT / COLOR)
            $table->foreignId('param_option_id')
                ->nullable()
                ->constrained('param_options')
                ->nullOnDelete();

            // Скалярные значения
            $table->string('value_string', 500)->nullable();   // STRING
            $table->text('value_text')->nullable();            // TEXT
            $table->integer('value_int')->nullable();          // INTEGER
            $table->decimal('value_float', 12, 4)->nullable(); // FLOAT / UNIT
            $table->boolean('value_boolean')->nullable();      // BOOLEAN

            // Диапазон
            $table->decimal('value_min', 12, 4)->nullable();   // RANGE min
            $table->decimal('value_max', 12, 4)->nullable();   // RANGE max

            $table->timestamps();

            // Один товар — один param_value на параметр (кроме MULTISELECT/COLOR — там несколько строк)
            $table->index(['product_id', 'param_id']);
            $table->index(['param_id', 'param_option_id']); // для фильтрации по опциям
            $table->index('value_string');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('product_param_values');
    }
};
