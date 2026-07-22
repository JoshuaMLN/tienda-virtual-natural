<?php

namespace App\Support\Orders;

use App\Enums\DeliveryStatus;
use App\Enums\OrderStatus;
use App\Enums\PaymentStatus;
use App\Enums\ReservationStatus;
use App\Models\Order;
use Carbon\CarbonInterface;

class CustomerOrderCapabilityResolver
{
    public function resolve(Order $order, ?CarbonInterface $now = null): CustomerOrderCapabilities
    {
        $now ??= now();
        $hasCurrentReservation = $this->hasCurrentReservation($order, $now);
        $isPendingPayment = $order->order_status === OrderStatus::PendingPayment
            && in_array($order->payment_status, [PaymentStatus::Pending, PaymentStatus::Failed], true)
            && $order->reservation_expires_at?->gt($now) === true
            && $hasCurrentReservation;

        return new CustomerOrderCapabilities(
            canCancel: $isPendingPayment,
            canContinuePayment: $isPendingPayment,
            shouldContactSupport: $order->payment_status === PaymentStatus::Paid
                && ! in_array($order->delivery_status, [DeliveryStatus::Delivered, DeliveryStatus::PickedUp], true),
        );
    }

    private function hasCurrentReservation(Order $order, CarbonInterface $now): bool
    {
        if ($order->relationLoaded('stockReservations')) {
            return $order->stockReservations->contains(
                fn ($reservation): bool => $reservation->status === ReservationStatus::Active
                    && $reservation->expires_at->gt($now),
            );
        }

        return $order->stockReservations()
            ->where('stock_reservations.status', ReservationStatus::Active->value)
            ->where('stock_reservations.expires_at', '>', $now)
            ->exists();
    }
}
