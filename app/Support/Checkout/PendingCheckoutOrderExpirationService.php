<?php

namespace App\Support\Checkout;

use App\Enums\OrderNotificationType;
use App\Enums\OrderStatus;
use App\Enums\PaymentStatus;
use App\Models\Order;
use App\Models\User;
use App\Support\Orders\Notifications\OrderNotificationDeliveryService;
use App\Support\Orders\Reservations\StockReservationService;
use Carbon\CarbonImmutable;
use DateTimeInterface;
use DomainException;
use Illuminate\Support\Facades\DB;

class PendingCheckoutOrderExpirationService
{
    private const MAX_LEGACY_PENDING_ORDERS = 100;

    public function __construct(
        private readonly PendingCheckoutOrderService $pendingOrders,
        private readonly StockReservationService $reservations,
        private readonly OrderNotificationDeliveryService $notifications,
    ) {}

    public function reconcileFor(
        User $user,
        ?DateTimeInterface $at = null,
    ): PendingCheckoutReconciliationResult {
        $moment = $at ? CarbonImmutable::instance($at) : CarbonImmutable::now();

        return DB::transaction(function () use ($user, $moment): PendingCheckoutReconciliationResult {
            User::query()->whereKey($user->getKey())->lockForUpdate()->firstOrFail();
            $expiredCodes = [];

            for ($attempt = 0; $attempt < self::MAX_LEGACY_PENDING_ORDERS; $attempt++) {
                $pendingOrder = $this->pendingOrders->findFor($user, lockForUpdate: true);

                if ($pendingOrder === null) {
                    return new PendingCheckoutReconciliationResult(null, $expiredCodes);
                }

                if (! $this->isDue($pendingOrder, $moment)) {
                    return new PendingCheckoutReconciliationResult($pendingOrder, $expiredCodes);
                }

                $expiredOrder = $this->expireIfDue($pendingOrder, $moment);

                if ($expiredOrder?->order_status === OrderStatus::Expired) {
                    $expiredCodes[] = $expiredOrder->code;
                }
            }

            throw new DomainException('Se encontraron demasiados pedidos pendientes para el mismo cliente.');
        }, 5);
    }

    public function expireIfDue(Order $order, ?DateTimeInterface $at = null): ?Order
    {
        $moment = $at ? CarbonImmutable::instance($at) : CarbonImmutable::now();

        return DB::transaction(function () use ($order, $moment): ?Order {
            $current = Order::query()->whereKey($order->getKey())->lockForUpdate()->firstOrFail();

            if ($current->order_status === OrderStatus::Expired
                && $current->payment_status === PaymentStatus::Expired) {
                return $current;
            }

            if ($current->order_status !== OrderStatus::PendingPayment
                || ! in_array($current->payment_status, [PaymentStatus::Pending, PaymentStatus::Failed], true)
                || ! $this->isDue($current, $moment)) {
                return null;
            }

            $expired = $this->reservations->expireForOrder($current, $moment);
            $this->notifications->record($expired, OrderNotificationType::Expired);

            return $expired;
        }, 5);
    }

    public function expireDue(int $limit = 100, ?DateTimeInterface $at = null): int
    {
        $moment = $at ? CarbonImmutable::instance($at) : CarbonImmutable::now();
        $limit = min(1_000, max(1, $limit));
        $orderIds = Order::query()
            ->where('order_status', OrderStatus::PendingPayment->value)
            ->whereIn('payment_status', [PaymentStatus::Pending->value, PaymentStatus::Failed->value])
            ->whereNotNull('reservation_expires_at')
            ->where('reservation_expires_at', '<=', $moment)
            ->oldest('id')
            ->limit($limit)
            ->pluck('id');
        $reconciled = 0;

        foreach ($orderIds as $orderId) {
            $expiredOrder = $this->expireIfDue(Order::query()->findOrFail($orderId), $moment);

            if ($expiredOrder?->order_status === OrderStatus::Expired) {
                $reconciled++;
            }
        }

        return $reconciled;
    }

    private function isDue(Order $order, CarbonImmutable $moment): bool
    {
        return $order->reservation_expires_at !== null
            && CarbonImmutable::instance($order->reservation_expires_at)->lessThanOrEqualTo($moment);
    }
}
