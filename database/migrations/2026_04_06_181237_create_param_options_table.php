<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('param_options', function (Blueprint $table) {
            $table->id();
            $table->foreignId('param_id')->constrained('params')->cascadeOnDelete();
            $table->string('value');
            $table->string('slug');
            $table->unsignedSmallInteger('sort')->default(0);
            $table->index(['param_id', 'slug'], 'param_options_param_slug_index');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('param_options');
    }
};
