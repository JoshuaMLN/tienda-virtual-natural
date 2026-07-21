<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->foreignId('pending_payment_owner_id')
                ->nullable()
                ->after('user_id')
                ->constrained('users')
                ->cascadeOnUpdate()
                ->nullOnDelete();
            $table->unique('pending_payment_owner_id');
        });

        $claimedOwners = [];
        $pendingOrders = DB::table('orders')
            ->select(['orders.id', 'orders.user_id'])
            ->whereNotNull('orders.user_id')
            ->where('orders.order_status', 'pending_payment')
            ->whereIn('orders.payment_status', ['pending', 'failed'])
            ->whereExists(function ($query): void {
                $query->selectRaw('1')
                    ->from('stock_reservations')
                    ->join('order_items', 'order_items.id', '=', 'stock_reservations.order_item_id')
                    ->whereColumn('order_items.order_id', 'orders.id')
                    ->where('stock_reservations.status', 'active');
            })
            ->orderBy('orders.user_id')
            ->orderBy('orders.id')
            ->get();

        foreach ($pendingOrders as $order) {
            $ownerId = (int) $order->user_id;

            if (isset($claimedOwners[$ownerId])) {
                continue;
            }

            DB::table('orders')
                ->where('id', $order->id)
                ->update(['pending_payment_owner_id' => $ownerId]);
            $claimedOwners[$ownerId] = true;
        }
    }

    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->dropUnique(['pending_payment_owner_id']);
            $table->dropConstrainedForeignId('pending_payment_owner_id');
        });
    }
};
