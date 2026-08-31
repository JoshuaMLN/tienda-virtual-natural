<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    private const WITH_CONFIRMED = [
        'pending_payment',
        'confirmed',
        'processing',
        'completed',
        'cancelled',
        'expired',
    ];

    private const WITHOUT_CONFIRMED = [
        'pending_payment',
        'processing',
        'completed',
        'cancelled',
        'expired',
    ];

    public function up(): void
    {
        Schema::table('orders', function (Blueprint $table): void {
            $table->enum('order_status', self::WITH_CONFIRMED)
                ->default('pending_payment')
                ->change();
        });

        DB::transaction(function (): void {
            $legacyOrderIds = DB::table('orders')
                ->where('order_status', 'pending_payment')
                ->where('payment_status', 'paid')
                ->orderBy('id')
                ->pluck('id');

            if ($legacyOrderIds->isEmpty()) {
                return;
            }

            DB::table('orders')
                ->whereIn('id', $legacyOrderIds)
                ->update([
                    'order_status' => 'confirmed',
                    'pending_payment_owner_id' => null,
                ]);

            $createdAt = now();

            foreach ($legacyOrderIds->chunk(500) as $orderIds) {
                DB::table('order_status_histories')->insert(
                    $orderIds->map(fn (int $orderId): array => [
                        'order_id' => $orderId,
                        'domain' => 'order',
                        'from_status' => 'pending_payment',
                        'to_status' => 'confirmed',
                        'actor_id' => null,
                        'actor_name' => null,
                        'actor_email' => null,
                        'reason' => 'Normalizacion del estado posterior al pago',
                        'metadata' => json_encode([
                            'source' => 'migration',
                            'migration' => '2026_08_04_000100_add_confirmed_to_orders_order_status',
                        ], JSON_THROW_ON_ERROR),
                        'created_at' => $createdAt,
                    ])->all(),
                );
            }
        });
    }

    public function down(): void
    {
        DB::table('orders')
            ->where('order_status', 'confirmed')
            ->update(['order_status' => 'pending_payment']);

        Schema::table('orders', function (Blueprint $table): void {
            $table->enum('order_status', self::WITHOUT_CONFIRMED)
                ->default('pending_payment')
                ->change();
        });
    }
};
