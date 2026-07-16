<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('delivery_districts', function (Blueprint $table) {
            $table->id();
            $table->char('ubigeo', 6)->unique();
            $table->char('province_code', 4)->index();
            $table->string('department', 80);
            $table->string('province', 80);
            $table->string('district', 100);
            $table->decimal('shipping_fee', 8, 2);
            $table->boolean('is_active')->default(true)->index();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('delivery_districts');
    }
};
