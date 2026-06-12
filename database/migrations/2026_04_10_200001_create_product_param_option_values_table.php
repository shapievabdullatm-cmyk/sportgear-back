<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Для MULTISELECT — несколько опций на один param
        Schema::create('product_param_option_values', function (Blueprint $table) {
            $table->id();
            $table->foreignId('product_id')->constrained('products')->cascadeOnDelete();
            $table->foreignId('param_id')->constrained('params')->cascadeOnDelete();
            $table->foreignId('param_option_id')->constrained('param_options')->cascadeOnDelete();
            $table->timestamps();

            $table->unique(['product_id', 'param_id', 'param_option_id'], 'uniq_product_param_option');
            $table->index(['product_id', 'param_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('product_param_option_values');
    }
};
