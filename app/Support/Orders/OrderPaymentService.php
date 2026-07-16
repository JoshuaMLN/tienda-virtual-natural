<?php

namespace App\Support\Orders;

use App\Enums\PaymentStatus;
use App\Models\Order;
use App\Models\User;
use App\Support\Orders\Reservations\StockReservationService;
use Illuminate\Support\Facades\DB;

class OrderPaymentService
{
    public function __construct(
        private readonly StockReservationService $reservations,
        private readonly OrderStateTransitionService $states,
    ) {}

    /** @param array<string, mixed> $metadata */
    public function markPaid(
        Order $order,
        ?User $actor = null,
        ?string $reason = null,
        array $metadata = [],
    ): Order {
        return DB::transaction(function () use ($order, $actor, $reason, $metadata): Order {
            $locked = Order::query()->whereKey($order->getKey())->lockForUpdate()->firstOrFail();

            $this->reservations->consumeForOrder($locked, $actor);

            return $this->states->transitionPayment(
                $locked->refresh(),
                PaymentStatus::Paid,
                $actor,
                $reason,
                $metadata,
            );
        });
    }
}
