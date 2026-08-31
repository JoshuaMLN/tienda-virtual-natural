<?php

namespace App\Support\Orders;

use App\Enums\DeliveryMethod;
use App\Enums\DeliveryStatus;
use App\Enums\OrderHistoryDomain;
use App\Enums\OrderStatus;
use App\Enums\PaymentStatus;
use App\Enums\ReservationStatus;
use App\Models\Order;
use App\Models\User;
use App\Support\Delivery\BusinessDayCalendar;
use Illuminate\Support\Facades\DB;

class OrderStateTransitionService
{
    private const ORDER_TRANSITIONS = [
        'pending_payment' => ['confirmed', 'cancelled', 'expired'],
        'confirmed' => ['processing', 'cancelled'],
        'processing' => ['completed', 'cancelled'],
        'completed' => [],
        'cancelled' => [],
        'expired' => [],
    ];

    private const PAYMENT_TRANSITIONS = [
        'pending' => ['paid', 'failed', 'expired'],
        'failed' => ['pending', 'paid', 'expired'],
        'paid' => ['refund_pending'],
        'expired' => [],
        'refund_pending' => ['refunded'],
        'refunded' => [],
    ];

    private const DELIVERY_TRANSITIONS = [
        'pending' => ['preparing', 'cancelled'],
        'preparing' => ['shipped', 'ready_for_pickup', 'cancelled'],
        'shipped' => ['delivered'],
        'ready_for_pickup' => ['picked_up', 'cancelled'],
        'delivered' => [],
        'picked_up' => [],
        'cancelled' => [],
    ];

    public function __construct(
        private readonly OrderHistoryRecorder $history,
        private readonly BusinessDayCalendar $calendar,
    ) {}

    /** @param array<string, mixed> $metadata */
    public function transitionOrder(
        Order $order,
        OrderStatus $target,
        ?User $actor = null,
        ?string $reason = null,
        array $metadata = [],
    ): Order {
        return DB::transaction(function () use ($order, $target, $actor, $reason, $metadata): Order {
            $locked = $this->lock($order);
            $current = $locked->order_status;

            if ($current === $target) {
                if ($target !== OrderStatus::PendingPayment) {
                    $locked->releasePendingPaymentSlot();
                }

                return $locked;
            }

            $this->ensureAllowed('pedido', self::ORDER_TRANSITIONS, $current->value, $target->value);

            if (in_array($target, [OrderStatus::Confirmed, OrderStatus::Processing], true)
                && $locked->payment_status !== PaymentStatus::Paid) {
                throw new InvalidStateTransitionException('pedido', $current->value, $target->value, 'El pedido solo puede procesarse despues de confirmar el pago.');
            }

            if (in_array($target, [OrderStatus::Confirmed, OrderStatus::Processing], true)
                && $this->hasActiveReservations($locked)) {
                throw new InvalidStateTransitionException('pedido', $current->value, $target->value, 'Las reservas deben consumirse antes de procesar el pedido.');
            }

            if (in_array($target, [OrderStatus::Cancelled, OrderStatus::Expired], true) && $this->hasActiveReservations($locked)) {
                throw new InvalidStateTransitionException('pedido', $current->value, $target->value, 'Las reservas activas deben liberarse antes de cerrar el pedido.');
            }

            if ($target === OrderStatus::Expired && $locked->payment_status !== PaymentStatus::Expired) {
                throw new InvalidStateTransitionException('pedido', $current->value, $target->value, 'El pago debe vencer antes de vencer el pedido.');
            }

            if ($target === OrderStatus::Cancelled && in_array($locked->delivery_status, [
                DeliveryStatus::Shipped,
                DeliveryStatus::Delivered,
                DeliveryStatus::PickedUp,
            ], true)) {
                throw new InvalidStateTransitionException('pedido', $current->value, $target->value, 'Un pedido enviado o entregado ya no puede cancelarse.');
            }

            if ($target === OrderStatus::Cancelled && ! in_array($locked->delivery_status, [
                DeliveryStatus::Pending,
                DeliveryStatus::Cancelled,
            ], true)) {
                throw new InvalidStateTransitionException('pedido', $current->value, $target->value, 'La preparacion o el recojo deben cancelarse antes de cancelar el pedido.');
            }

            if ($target === OrderStatus::Completed && ! $this->deliveryIsComplete($locked)) {
                throw new InvalidStateTransitionException('pedido', $current->value, $target->value, 'El pedido solo puede completarse despues de finalizar la entrega o el recojo.');
            }

            $attributes = ['order_status' => $target];

            if ($target === OrderStatus::Cancelled) {
                $attributes['cancelled_at'] = now();
            } elseif ($target === OrderStatus::Expired) {
                $attributes['expired_at'] = now();
            } elseif ($target === OrderStatus::Completed) {
                $attributes['completed_at'] = now();
            }

            if ($target !== OrderStatus::PendingPayment) {
                $attributes['pending_payment_owner_id'] = null;
            }

            $locked->applyStateMutation($attributes);
            $this->history->record($locked, OrderHistoryDomain::Order, $current->value, $target->value, $actor, $reason, $metadata);

            return $locked->refresh();
        });
    }

    /** @param array<string, mixed> $metadata */
    public function transitionPayment(
        Order $order,
        PaymentStatus $target,
        ?User $actor = null,
        ?string $reason = null,
        array $metadata = [],
    ): Order {
        return DB::transaction(function () use ($order, $target, $actor, $reason, $metadata): Order {
            $locked = $this->lock($order);
            $current = $locked->payment_status;

            if ($current === $target) {
                if (in_array($target, [
                    PaymentStatus::Paid,
                    PaymentStatus::Expired,
                    PaymentStatus::RefundPending,
                    PaymentStatus::Refunded,
                ], true)) {
                    $locked->releasePendingPaymentSlot();
                }

                if ($target === PaymentStatus::Paid
                    && $locked->order_status === OrderStatus::PendingPayment) {
                    return $this->transitionOrder(
                        $locked->refresh(),
                        OrderStatus::Confirmed,
                        $actor,
                        $reason,
                        $metadata,
                    );
                }

                return $locked;
            }

            $this->ensureAllowed('pago', self::PAYMENT_TRANSITIONS, $current->value, $target->value);

            if ($target === PaymentStatus::Paid && in_array($locked->order_status, [OrderStatus::Cancelled, OrderStatus::Expired], true)) {
                throw new InvalidStateTransitionException('pago', $current->value, $target->value, 'No se puede pagar un pedido cancelado o vencido.');
            }

            if ($target === PaymentStatus::Paid && $locked->order_status !== OrderStatus::PendingPayment) {
                throw new InvalidStateTransitionException('pago', $current->value, $target->value, 'Solo un pedido pendiente de pago puede confirmar su pago inicial.');
            }

            if ($target === PaymentStatus::Expired && $locked->order_status !== OrderStatus::PendingPayment) {
                throw new InvalidStateTransitionException('pago', $current->value, $target->value, 'Solo un pedido pendiente de pago puede vencer.');
            }

            if ($target === PaymentStatus::Paid && $locked->stockReservations()
                ->where('status', ReservationStatus::Active->value)
                ->where('expires_at', '<=', now())
                ->exists()) {
                throw new InvalidStateTransitionException('pago', $current->value, $target->value, 'No se puede pagar un pedido con reservas vencidas.');
            }

            if ($target === PaymentStatus::Paid && $this->hasActiveReservations($locked)) {
                throw new InvalidStateTransitionException('pago', $current->value, $target->value, 'Las reservas deben consumirse mediante el servicio de pago antes de confirmarlo.');
            }

            $attributes = ['payment_status' => $target];

            if ($target === PaymentStatus::Paid) {
                $paidAt = now();
                $attributes['paid_at'] = $paidAt;
                $attributes['delivery_window_starts_at'] = $paidAt;

                if ($locked->delivery_estimated_from === null || $locked->delivery_estimated_to === null) {
                    $estimatedDates = $this->calendar->estimate(
                        $locked->delivery_business_days_min,
                        $locked->delivery_business_days_max,
                        $paidAt,
                    );
                    $attributes['delivery_estimated_from'] = $estimatedDates->from;
                    $attributes['delivery_estimated_to'] = $estimatedDates->to;
                }
            }

            if (in_array($target, [
                PaymentStatus::Paid,
                PaymentStatus::Expired,
                PaymentStatus::RefundPending,
                PaymentStatus::Refunded,
            ], true)) {
                $attributes['pending_payment_owner_id'] = null;
            }

            $locked->applyStateMutation($attributes);
            $this->history->record($locked, OrderHistoryDomain::Payment, $current->value, $target->value, $actor, $reason, $metadata);

            $updated = $locked->refresh();

            if ($target === PaymentStatus::Paid) {
                return $this->transitionOrder(
                    $updated,
                    OrderStatus::Confirmed,
                    $actor,
                    $reason,
                    $metadata,
                );
            }

            return $updated;
        });
    }

    /** @param array<string, mixed> $metadata */
    public function transitionDelivery(
        Order $order,
        DeliveryStatus $target,
        ?User $actor = null,
        ?string $reason = null,
        array $metadata = [],
    ): Order {
        return DB::transaction(function () use ($order, $target, $actor, $reason, $metadata): Order {
            $locked = $this->lock($order);
            $current = $locked->delivery_status;

            if ($current === $target) {
                return $locked;
            }

            $this->ensureAllowed('entrega', self::DELIVERY_TRANSITIONS, $current->value, $target->value);

            if ($target !== DeliveryStatus::Cancelled && $locked->payment_status !== PaymentStatus::Paid) {
                throw new InvalidStateTransitionException('entrega', $current->value, $target->value, 'La preparacion de la entrega requiere un pago confirmado.');
            }

            $this->ensureDeliveryMethodMatches($locked, $current, $target);

            $locked->applyStateMutation(['delivery_status' => $target]);
            $this->history->record($locked, OrderHistoryDomain::Delivery, $current->value, $target->value, $actor, $reason, $metadata);

            return $locked->refresh();
        });
    }

    /** @param array<string, list<string>> $transitions */
    private function ensureAllowed(string $domain, array $transitions, string $from, string $to): void
    {
        if (! in_array($to, $transitions[$from] ?? [], true)) {
            throw new InvalidStateTransitionException($domain, $from, $to);
        }
    }

    private function ensureDeliveryMethodMatches(Order $order, DeliveryStatus $from, DeliveryStatus $target): void
    {
        $homeOnly = [DeliveryStatus::Shipped, DeliveryStatus::Delivered];
        $pickupOnly = [DeliveryStatus::ReadyForPickup, DeliveryStatus::PickedUp];

        if ($order->delivery_method === DeliveryMethod::Pickup && in_array($target, $homeOnly, true)) {
            throw new InvalidStateTransitionException('entrega', $from->value, $target->value, 'Un pedido con recojo no puede pasar por estados de envio.');
        }

        if ($order->delivery_method === DeliveryMethod::HomeDelivery && in_array($target, $pickupOnly, true)) {
            throw new InvalidStateTransitionException('entrega', $from->value, $target->value, 'Un pedido con entrega a domicilio no puede pasar por estados de recojo.');
        }
    }

    private function deliveryIsComplete(Order $order): bool
    {
        return ($order->delivery_method === DeliveryMethod::HomeDelivery && $order->delivery_status === DeliveryStatus::Delivered)
            || ($order->delivery_method === DeliveryMethod::Pickup && $order->delivery_status === DeliveryStatus::PickedUp);
    }

    private function hasActiveReservations(Order $order): bool
    {
        return $order->stockReservations()
            ->where('status', ReservationStatus::Active->value)
            ->exists();
    }

    private function lock(Order $order): Order
    {
        return Order::query()->whereKey($order->getKey())->lockForUpdate()->firstOrFail();
    }
}
