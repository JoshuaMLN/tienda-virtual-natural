<?php

namespace App\Support\Checkout;

use App\Enums\OrderStatus;
use App\Enums\PaymentStatus;
use App\Enums\ReservationStatus;
use App\Models\Order;
use App\Models\User;
use App\Support\Orders\Reservations\StockReservationService;
use DomainException;
use Illuminate\Support\Facades\DB;

class PendingCheckoutOrderService
{
    public function __construct(
        private readonly StockReservationService $reservations,
    ) {}

    public function findFor(User $user, bool $lockForUpdate = false): ?Order
    {
        $query = Order::query()
            ->with(['items', 'stockReservations'])
            ->where('user_id', $user->getKey())
            ->where('order_status', OrderStatus::PendingPayment->value)
            ->whereIn('payment_status', [PaymentStatus::Pending->value, PaymentStatus::Failed->value])
            ->where(function ($query) use ($user): void {
                $query->where('pending_payment_owner_id', $user->getKey())
                    ->orWhere(function ($legacy) use ($user): void {
                        $legacy->whereNull('pending_payment_owner_id')
                            ->where('user_id', $user->getKey())
                            ->whereHas('stockReservations', function ($reservations): void {
                                $reservations->where('stock_reservations.status', ReservationStatus::Active->value);
                            });
                    });
            })
            ->orderByRaw('CASE WHEN pending_payment_owner_id IS NULL THEN 1 ELSE 0 END')
            ->oldest('orders.id');

        if ($lockForUpdate) {
            $query->lockForUpdate();
        }

        return $query->first();
    }

    public function isPending(Order $order): bool
    {
        return $order->order_status === OrderStatus::PendingPayment
            && in_array($order->payment_status, [PaymentStatus::Pending, PaymentStatus::Failed], true)
            && ($order->pending_payment_owner_id !== null
                || $order->stockReservations()
                    ->where('stock_reservations.status', ReservationStatus::Active->value)
                    ->exists());
    }

    public function cancel(User $user, Order $order): Order
    {
        return DB::transaction(function () use ($user, $order): Order {
            $locked = Order::query()->whereKey($order->getKey())->lockForUpdate()->firstOrFail();

            if ((int) $locked->user_id !== (int) $user->getKey()) {
                throw new DomainException('El pedido no pertenece al cliente autenticado.');
            }

            if ($locked->order_status === OrderStatus::Cancelled) {
                $locked->releasePendingPaymentSlot();

                return $locked->refresh();
            }

            if ($locked->order_status !== OrderStatus::PendingPayment
                || ! in_array($locked->payment_status, [PaymentStatus::Pending, PaymentStatus::Failed], true)) {
                throw new DomainException('Este pedido ya no se puede cancelar desde el checkout.');
            }

            return $this->reservations->releaseForCancellation(
                $locked,
                $user,
                'Cancelado por el cliente desde el checkout',
            );
        }, 5);
    }
}
