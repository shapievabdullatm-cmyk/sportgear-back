<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('popular_categories', function (Blueprint $table) {
            $table->id();

            $table->foreignId('category_id')
                ->constrained('categories')
                ->cascadeOnDelete();

            $table->unsignedInteger('position')->default(0);

            $table->timestamps();

            $table->unique('category_id');           // одна категория — один раз
            $table->index('position');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('popular_categories');
    }
};
