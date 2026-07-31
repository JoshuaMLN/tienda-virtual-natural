<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('stock_reservations', function (Blueprint $table): void {
            $table->foreignId('restock_inventory_movement_id')
                ->nullable()
                ->unique()
                ->after('release_inventory_movement_id')
                ->constrained('inventory_movements')
                ->cascadeOnUpdate()
                ->nullOnDelete();
            $table->timestamp('restocked_at')->nullable()->after('expired_at');
            $table->string('restock_reason')->nullable()->after('release_reason');
        });
    }

    public function down(): void
    {
        Schema::table('stock_reservations', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('restock_inventory_movement_id');
            $table->dropColumn(['restocked_at', 'restock_reason']);
        });
    }
};
