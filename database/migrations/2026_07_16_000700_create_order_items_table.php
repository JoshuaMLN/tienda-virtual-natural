<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('order_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('order_id')->constrained()->cascadeOnUpdate()->restrictOnDelete();
            $table->foreignId('product_id')->nullable()->constrained()->cascadeOnUpdate()->nullOnDelete();
            $table->string('product_sku')->index();
            $table->string('product_name');
            $table->string('product_image')->nullable();
            $table->string('product_presentation')->nullable();
            $table->string('sale_unit', 40)->default('unidad');
            $table->unsignedInteger('quantity');
            $table->enum('tax_affectation', ['taxed', 'exempt', 'unaffected']);
            $table->unsignedSmallInteger('tax_rate_bps')->default(1800);
            $table->unsignedBigInteger('unit_price_cents');
            $table->unsignedBigInteger('gross_total_cents');
            $table->unsignedBigInteger('discount_cents')->default(0);
            $table->unsignedBigInteger('net_value_cents');
            $table->unsignedBigInteger('tax_cents')->default(0);
            $table->unsignedBigInteger('total_cents');
            $table->timestamps();

            $table->index(['order_id', 'product_id']);
            $table->index(['order_id', 'tax_affectation']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('order_items');
    }
};
