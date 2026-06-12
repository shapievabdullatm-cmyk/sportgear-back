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
        Schema::create('category_params', function (Blueprint $table) {
            $table->foreignId('category_id')->constrained('categories')->cascadeOnDelete();
            $table->foreignId('param_id')->constrained('params')->cascadeOnDelete();

            $table->integer('sort')->default(0);
            $table->boolean('is_required')->default(false);

            $table->primary(['category_id', 'param_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('category_params');
    }
};
