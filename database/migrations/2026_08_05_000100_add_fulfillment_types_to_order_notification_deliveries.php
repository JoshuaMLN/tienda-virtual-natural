<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    private const TYPES = [
        'created',
        'cancelled',
        'expired',
        'shipped',
        'pickup_ready',
        'delivered',
        'picked_up',
        'pickup_midpoint_reminder',
        'pickup_48_hours_reminder',
        'pickup_deadline_reminder',
    ];

    public function up(): void
    {
        Schema::table('order_notification_deliveries', function (Blueprint $table): void {
            $table->enum('type', self::TYPES)->change();
        });
    }

    public function down(): void
    {
        Schema::table('order_notification_deliveries', function (Blueprint $table): void {
            $table->enum('type', ['created', 'cancelled', 'expired'])->change();
        });
    }
};
