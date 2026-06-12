<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new

class extends Migration {
    public function up(): void
    {
        Schema::create('addresses', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('title', 100)->default('Дом');
            $table->string('full_address', 500);
            $table->decimal('lat', 10, 7)->nullable();
            $table->decimal('lon', 10, 7)->nullable();
            $table->string('city', 100)->nullable();
            $table->string('street', 200)->nullable();
            $table->string('house', 20)->nullable();
            $table->string('apartment', 20)->nullable();
            $table->string('entrance', 10)->nullable();
            $table->string('floor', 10)->nullable();
            $table->string('intercom', 20)->nullable();
            $table->text('comment')->nullable();
            $table->boolean('is_default')->default(false);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('addresses');
    }
};
