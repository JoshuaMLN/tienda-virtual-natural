<?php

use Carbon\CarbonImmutable;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('orders', function (Blueprint $table): void {
            $table->enum('delivery_tracking_status', [
                'active',
                'awaiting_reshipment_payment',
                'manual_follow_up',
                'completed',
            ])->default('active')->after('delivery_status');
            $table->unsignedTinyInteger('delivery_current_cycle')->default(1)->after('delivery_tracking_status');
            $table->unsignedTinyInteger('delivery_attempts_per_cycle')->default(3)->after('delivery_current_cycle');
            $table->unsignedTinyInteger('delivery_max_automatic_cycles')->default(2)->after('delivery_attempts_per_cycle');
            $table->unsignedTinyInteger('reshipment_payment_days')->default(7)->after('delivery_max_automatic_cycles');
            $table->unsignedSmallInteger('pickup_hold_days')->default(14)->after('reshipment_payment_days');
            $table->timestamp('reshipment_payment_due_at')->nullable()->after('pickup_hold_days');
            $table->timestamp('pickup_ready_at')->nullable()->after('reshipment_payment_due_at');
            $table->timestamp('pickup_deadline_at')->nullable()->after('pickup_ready_at');
            $table->timestamp('delivery_manual_follow_up_at')->nullable()->after('pickup_deadline_at');
            $table->timestamp('delivery_tracking_completed_at')->nullable()->after('delivery_manual_follow_up_at');

            $table->index(
                ['delivery_tracking_status', 'reshipment_payment_due_at'],
                'orders_delivery_tracking_due_index',
            );
            $table->index(
                ['delivery_method', 'delivery_status', 'pickup_deadline_at'],
                'orders_pickup_deadline_index',
            );
        });

        DB::table('orders')
            ->select([
                'id',
                'order_status',
                'delivery_status',
                'terms_snapshot',
                'completed_at',
                'cancelled_at',
                'expired_at',
                'updated_at',
            ])
            ->orderBy('id')
            ->chunkById(100, function ($orders): void {
                foreach ($orders as $order) {
                    $settings = $this->settingsSnapshot($order->terms_snapshot);
                    $trackingStatus = in_array($order->order_status, ['completed', 'cancelled', 'expired'], true)
                        || in_array($order->delivery_status, ['delivered', 'picked_up', 'cancelled'], true)
                            ? 'completed'
                            : 'active';
                    $pickupReadyAt = null;
                    $pickupDeadlineAt = null;
                    $manualFollowUpAt = null;

                    if ($order->delivery_status === 'ready_for_pickup') {
                        $pickupReadyAt = DB::table('order_status_histories')
                            ->where('order_id', $order->id)
                            ->where('domain', 'delivery')
                            ->where('to_status', 'ready_for_pickup')
                            ->orderBy('created_at')
                            ->orderBy('id')
                            ->value('created_at') ?? $order->updated_at;
                        $pickupDeadlineAt = CarbonImmutable::parse($pickupReadyAt)
                            ->addDays($settings['pickup_hold_days']);

                        if ($pickupDeadlineAt->lte(now())) {
                            $trackingStatus = 'manual_follow_up';
                            $manualFollowUpAt = now();
                        }
                    }

                    DB::table('orders')->where('id', $order->id)->update([
                        ...$settings,
                        'delivery_tracking_status' => $trackingStatus,
                        'pickup_ready_at' => $pickupReadyAt,
                        'pickup_deadline_at' => $pickupDeadlineAt,
                        'delivery_manual_follow_up_at' => $manualFollowUpAt,
                        'delivery_tracking_completed_at' => $trackingStatus === 'completed'
                            ? ($order->completed_at ?? $order->cancelled_at ?? $order->expired_at ?? $order->updated_at)
                            : null,
                    ]);
                }
            });
    }

    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table): void {
            $table->dropIndex('orders_delivery_tracking_due_index');
            $table->dropIndex('orders_pickup_deadline_index');
            $table->dropColumn([
                'delivery_tracking_status',
                'delivery_current_cycle',
                'delivery_attempts_per_cycle',
                'delivery_max_automatic_cycles',
                'reshipment_payment_days',
                'pickup_hold_days',
                'reshipment_payment_due_at',
                'pickup_ready_at',
                'pickup_deadline_at',
                'delivery_manual_follow_up_at',
                'delivery_tracking_completed_at',
            ]);
        });
    }

    /** @return array<string, int> */
    private function settingsSnapshot(mixed $termsSnapshot): array
    {
        $snapshot = is_string($termsSnapshot)
            ? json_decode($termsSnapshot, true)
            : (is_array($termsSnapshot) ? $termsSnapshot : []);
        $settings = is_array($snapshot['settings_snapshot'] ?? null)
            ? $snapshot['settings_snapshot']
            : [];

        return [
            'delivery_attempts_per_cycle' => $this->bounded($settings['delivery_attempts_per_cycle'] ?? null, 1, 10, 3),
            'delivery_max_automatic_cycles' => $this->bounded($settings['delivery_max_automatic_cycles'] ?? null, 1, 5, 2),
            'reshipment_payment_days' => $this->bounded($settings['reshipment_payment_days'] ?? null, 1, 30, 7),
            'pickup_hold_days' => $this->bounded($settings['pickup_hold_days'] ?? null, 1, 60, 14),
        ];
    }

    private function bounded(mixed $value, int $minimum, int $maximum, int $fallback): int
    {
        if (! is_numeric($value)) {
            return $fallback;
        }

        return min($maximum, max($minimum, (int) $value));
    }
};
