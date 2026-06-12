<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('products', function (Blueprint $table) {
            $table->id();
            $table->string('external_id')->nullable()->unique();
            $table->string('title')->nullable();
            $table->string('external_title')->nullable();
            $table->string('slug')->unique();
            $table->string('article')->nullable();
            $table->text('description')->nullable();
            $table->decimal('price', 8, 2)->nullable();
            $table->decimal('old_price', 8, 2)->nullable();
            $table->string('meta_title')->nullable();
            $table->text('meta_description')->nullable();
            $table->text('meta_keywords')->nullable();
            $table->integer('weight')->nullable();
            $table->decimal('length', 8, 2)->nullable();
            $table->decimal('width', 8, 2)->nullable();
            $table->decimal('height', 8, 2)->nullable();
            $table->boolean('is_active')->default(false);

            $table->foreignId('parent_id')->nullable()->constrained('products')->nullOnDelete();
            $table->foreignId('category_id')->nullable()->constrained('categories')->nullOnDelete();
            $table->foreignId('product_group_id')->nullable()->constrained('product_groups')->nullOnDelete();
            $table->foreignId('brand_id')->nullable()->constrained('brands')->nullOnDelete();
            $table->foreignId('brand_origin_id')->nullable()->constrained('brand_origins')->nullOnDelete();
            $table->foreignId('manufacturing_country_id')->nullable()->constrained('manufacturing_countries')->nullOnDelete();
            $table->foreignId('size_table_id')->nullable()->constrained('size_tables')->nullOnDelete();
            $table->timestamps();

            $table->index('title');
            $table->index('article');
            $table->index(['is_active', 'title']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('products');
    }
};
