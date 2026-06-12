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
        Schema::create('sliders', function (Blueprint $table) {
            $table->id();
            $table->string('title');
            $table->string('link');
            $table->string('image')->nullable();       // S3: sliders/{uuid}.webp
            $table->string('mobile_image')->nullable();
            $table->unsignedInteger('position')->default(0);
            $table->timestamps();

            $table->index('position');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('sliders');
    }
};
