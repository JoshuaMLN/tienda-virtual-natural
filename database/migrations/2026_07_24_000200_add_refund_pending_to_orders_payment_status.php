<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    private const WITH_REFUND_PENDING = [
        'pending',
        'paid',
        'failed',
        'expired',
        'refund_pending',
        'refunded',
    ];

    private const WITHOUT_REFUND_PENDING = [
        'pending',
        'paid',
        'failed',
        'expired',
        'refunded',
    ];

    public function up(): void
    {
        Schema::table('orders', function (Blueprint $table): void {
            $table->enum('payment_status', self::WITH_REFUND_PENDING)
                ->default('pending')
                ->change();
        });
    }

    public function down(): void
    {
        DB::table('orders')
            ->where('payment_status', 'refund_pending')
            ->update(['payment_status' => 'paid']);

        Schema::table('orders', function (Blueprint $table): void {
            $table->enum('payment_status', self::WITHOUT_REFUND_PENDING)
                ->default('pending')
                ->change();
        });
    }
};
