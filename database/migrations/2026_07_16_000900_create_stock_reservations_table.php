<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('stock_reservations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('order_item_id')->unique()->constrained()->cascadeOnUpdate()->restrictOnDelete();
            $table->foreignId('reserve_inventory_movement_id')->nullable()->unique()->constrained('inventory_movements')->nullOnDelete();
            $table->foreignId('release_inventory_movement_id')->nullable()->unique()->constrained('inventory_movements')->nullOnDelete();
            $table->unsignedInteger('quantity');
            $table->enum('status', ['active', 'consumed', 'released', 'expired'])->default('active');
            $table->timestamp('expires_at');
            $table->timestamp('consumed_at')->nullable();
            $table->timestamp('released_at')->nullable();
            $table->timestamp('expired_at')->nullable();
            $table->string('release_reason')->nullable();
            $table->timestamps();

            $table->index(['status', 'expires_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('stock_reservations');
    }
};
