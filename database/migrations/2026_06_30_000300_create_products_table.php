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
            $table->foreignId('category_id')->constrained()->cascadeOnUpdate()->restrictOnDelete();
            $table->foreignId('brand_id')->nullable()->constrained()->nullOnDelete();
            $table->string('name');
            $table->string('slug')->unique();
            $table->string('sku')->unique();
            $table->string('short_description')->nullable();
            $table->text('description')->nullable();
            $table->text('benefits')->nullable();
            $table->text('ingredients')->nullable();
            $table->text('usage_instructions')->nullable();
            $table->decimal('price', 10, 2);
            $table->decimal('compare_at_price', 10, 2)->nullable();
            $table->unsignedInteger('stock')->default(0);
            $table->decimal('rating_average', 3, 2)->default(0);
            $table->unsignedInteger('reviews_count')->default(0);
            $table->boolean('is_active')->default(true)->index();
            $table->boolean('is_featured')->default(false)->index();
            $table->timestamp('published_at')->nullable()->index();
            $table->timestamps();

            $table->index(['category_id', 'is_active']);
            $table->index(['brand_id', 'is_active']);
            $table->index(['price', 'is_active']);
            $table->index(['created_at', 'is_active']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('products');
    }
};
