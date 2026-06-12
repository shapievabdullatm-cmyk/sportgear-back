<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('shops', function (Blueprint $table) {
            $table->id();
            $table->string('external_id')->nullable()->unique();
            $table->string('slug')->nullable()->unique();
            $table->string('name');
            $table->text('description')->nullable();

            // Адрес и координаты
            $table->string('address')->nullable();
            $table->string('city')->nullable();
            $table->decimal('latitude', 10, 7)->nullable();
            $table->decimal('longitude', 10, 7)->nullable();

            // Контакты
            $table->string('phone')->nullable();
            $table->string('email')->nullable();

            // График работы — массив по дням недели
            // [{"day":1,"is_open":true,"open":"10:00","close":"22:00","break":[{"from":"14:00","to":"15:00"}]}, ...]
            $table->json('working_hours')->nullable();

            // Расширяемые данные (метро, ссылки на соцсети, заметки, фото и т.п.)
            $table->json('metadata')->nullable();

            $table->boolean('is_active')->default(true);
            $table->integer('sort_order')->default(0);

            $table->timestamps();

            $table->index('is_active');
            $table->index('sort_order');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('shops');
    }
};