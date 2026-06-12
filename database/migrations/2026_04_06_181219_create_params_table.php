<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('params', function (Blueprint $table) {
            $table->id();
            $table->string('title');
            $table->string('slug')->unique();
            $table->string('filter_type')->nullable();
            $table->string('unit', 32)->nullable();
            $table->boolean('is_filterable')->default(true);
            $table->boolean('is_searchable')->default(false);
            $table->boolean('is_comparable')->default(false);
            $table->boolean('is_size')->default(false);
            $table->string('label')->nullable();
            $table->unsignedSmallInteger('sort')->default(0);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('params');
    }
};
