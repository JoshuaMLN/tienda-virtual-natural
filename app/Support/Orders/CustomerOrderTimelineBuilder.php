<?php

namespace App\Support\Orders;

use App\Enums\DeliveryMethod;
use App\Enums\DeliveryStatus;
use App\Enums\OrderHistoryDomain;
use App\Enums\OrderStatus;
use App\Enums\PaymentStatus;
use App\Models\Order;
use App\Models\OrderStatusHistory;

class CustomerOrderTimelineBuilder
{
    /** @return list<CustomerOrderTimelineEvent> */
    public function build(Order $order): array
    {
        $events = [
            new CustomerOrderTimelineEvent(
                'Pedido creado',
                'Recibimos tu pedido y registramos sus productos.',
                'bi-receipt',
                'neutral',
                $order->created_at,
            ),
        ];
        $deduplicatedGroups = [];

        foreach ($order->statusHistories as $history) {
            if ($history->from_status === null) {
                continue;
            }

            $mapped = $this->map($order, $history);

            if ($mapped === null) {
                continue;
            }

            if ($mapped['group'] !== null) {
                if (isset($deduplicatedGroups[$mapped['group']])) {
                    continue;
                }

                $deduplicatedGroups[$mapped['group']] = true;
            }

            $events[] = $mapped['event'];
        }

        return $events;
    }

    /**
     * @return array{group: ?string, event: CustomerOrderTimelineEvent}|null
     */
    private function map(Order $order, OrderStatusHistory $history): ?array
    {
        return match ($history->domain) {
            OrderHistoryDomain::Order => $this->mapOrder($order, $history),
            OrderHistoryDomain::Payment => $this->mapPayment($history),
            OrderHistoryDomain::Delivery => $this->mapDelivery($history),
            OrderHistoryDomain::Reservation => null,
        };
    }

    /** @return array{group: ?string, event: CustomerOrderTimelineEvent}|null */
    private function mapOrder(Order $order, OrderStatusHistory $history): ?array
    {
        return match ($history->to_status) {
            OrderStatus::Processing->value => $this->event(
                'preparing',
                'Pedido en preparacion',
                'Estamos preparando los productos de tu pedido.',
                'bi-box-seam',
                'info',
                $history,
            ),
            OrderStatus::Completed->value => $this->event(
                'completed',
                $order->delivery_method === DeliveryMethod::Pickup ? 'Pedido recogido' : 'Pedido entregado',
                $order->delivery_method === DeliveryMethod::Pickup
                    ? 'El pedido fue recogido en tienda.'
                    : 'El pedido fue entregado en la direccion indicada.',
                'bi-check-circle',
                'success',
                $history,
            ),
            OrderStatus::Cancelled->value => $this->event(
                'cancelled',
                'Pedido cancelado',
                'El pedido fue cancelado y ya no continuara su procesamiento.',
                'bi-x-circle',
                'danger',
                $history,
            ),
            OrderStatus::Expired->value => $this->event(
                'expired',
                'Pedido vencido',
                'La reserva vencio antes de confirmarse el pago.',
                'bi-hourglass-bottom',
                'danger',
                $history,
            ),
            default => null,
        };
    }

    /** @return array{group: ?string, event: CustomerOrderTimelineEvent}|null */
    private function mapPayment(OrderStatusHistory $history): ?array
    {
        return match ($history->to_status) {
            PaymentStatus::Paid->value => $this->event(
                null,
                'Pago confirmado',
                'El pago fue confirmado correctamente.',
                'bi-credit-card',
                'success',
                $history,
            ),
            PaymentStatus::Failed->value => $this->event(
                null,
                'Pago no completado',
                'No pudimos confirmar el pago.',
                'bi-exclamation-circle',
                'warning',
                $history,
            ),
            PaymentStatus::Pending->value => $this->event(
                null,
                'Pago pendiente',
                'El pedido quedo a la espera de un nuevo intento de pago.',
                'bi-clock',
                'warning',
                $history,
            ),
            PaymentStatus::Refunded->value => $this->event(
                null,
                'Pago reembolsado',
                'El pago del pedido fue reembolsado.',
                'bi-arrow-counterclockwise',
                'neutral',
                $history,
            ),
            PaymentStatus::RefundPending->value => $this->event(
                null,
                'Reembolso pendiente',
                'La cancelacion fue registrada y el reembolso esta pendiente de confirmacion.',
                'bi-arrow-counterclockwise',
                'warning',
                $history,
            ),
            PaymentStatus::Expired->value => $this->event(
                'expired',
                'Pedido vencido',
                'La reserva vencio antes de confirmarse el pago.',
                'bi-hourglass-bottom',
                'danger',
                $history,
            ),
            default => null,
        };
    }

    /** @return array{group: ?string, event: CustomerOrderTimelineEvent}|null */
    private function mapDelivery(OrderStatusHistory $history): ?array
    {
        return match ($history->to_status) {
            DeliveryStatus::Preparing->value => $this->event(
                'preparing',
                'Pedido en preparacion',
                'Estamos preparando los productos de tu pedido.',
                'bi-box-seam',
                'info',
                $history,
            ),
            DeliveryStatus::Shipped->value => $this->event(
                null,
                'Pedido en camino',
                'Tu pedido salio rumbo a la direccion de entrega.',
                'bi-truck',
                'progress',
                $history,
            ),
            DeliveryStatus::ReadyForPickup->value => $this->event(
                null,
                'Listo para recoger',
                'Tu pedido ya esta disponible para recojo.',
                'bi-shop',
                'progress',
                $history,
            ),
            DeliveryStatus::Delivered->value => $this->event(
                'completed',
                'Pedido entregado',
                'El pedido fue entregado en la direccion indicada.',
                'bi-check-circle',
                'success',
                $history,
            ),
            DeliveryStatus::PickedUp->value => $this->event(
                'completed',
                'Pedido recogido',
                'El pedido fue recogido en tienda.',
                'bi-check-circle',
                'success',
                $history,
            ),
            DeliveryStatus::Cancelled->value => $this->event(
                'cancelled',
                'Pedido cancelado',
                'El pedido fue cancelado y ya no continuara su procesamiento.',
                'bi-x-circle',
                'danger',
                $history,
            ),
            default => null,
        };
    }

    /** @return array{group: ?string, event: CustomerOrderTimelineEvent} */
    private function event(
        ?string $group,
        string $title,
        string $description,
        string $icon,
        string $tone,
        OrderStatusHistory $history,
    ): array {
        return [
            'group' => $group,
            'event' => new CustomerOrderTimelineEvent(
                $title,
                $description,
                $icon,
                $tone,
                $history->created_at,
            ),
        ];
    }
}
