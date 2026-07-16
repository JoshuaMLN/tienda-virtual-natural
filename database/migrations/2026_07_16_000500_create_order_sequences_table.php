<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('order_sequences', function (Blueprint $table) {
            $table->unsignedSmallInteger('year')->primary();
            // DECIMAL(6, 0) makes the annual six-digit limit a MySQL constraint too.
            $table->decimal('last_number', 6, 0)->unsigned();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('order_sequences');
    }
};
