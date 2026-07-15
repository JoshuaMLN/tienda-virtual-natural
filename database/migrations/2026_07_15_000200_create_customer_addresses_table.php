<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('customer_addresses', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnUpdate()->cascadeOnDelete();
            $table->string('label', 50);
            $table->string('recipient_name', 120);
            $table->string('phone', 20);
            $table->string('department', 80);
            $table->string('province', 80);
            $table->string('district', 100);
            $table->char('ubigeo', 6);
            $table->string('address_line');
            $table->string('reference')->nullable();
            $table->boolean('is_default')->default(false);
            $table->timestamps();

            $table->index(['user_id', 'is_default']);
            $table->index(['user_id', 'created_at']);
            $table->index('ubigeo');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('customer_addresses');
    }
};
