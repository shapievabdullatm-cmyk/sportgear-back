<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('categories', function (Blueprint $table) {
            $table->id();
            $table->string('title');
            $table->string('slug')->unique();
            $table->string('image')->nullable();       // S3 path: categories/{uuid}.webp
            $table->string('meta_title')->nullable();
            $table->text('meta_description')->nullable();
            $table->text('keywords')->nullable();       // через запятую

            $table->foreignId('parent_id')
                ->nullable()
                ->constrained('categories')
                ->nullOnDelete();

            $table->unsignedInteger('position')->default(0);

            $table->timestamps();

            $table->index(['parent_id', 'position']);
            $table->index('slug');                     // для быстрого поиска по slug на клиенте
            $table->index('title');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('categories');
    }
};
