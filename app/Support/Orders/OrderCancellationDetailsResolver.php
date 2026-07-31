<?php

namespace App\Support\Orders;

use App\Enums\OrderHistoryDomain;
use App\Enums\OrderStatus;
use App\Enums\PaymentStatus;
use App\Models\Order;
use App\Models\OrderStatusHistory;

class OrderCancellationDetailsResolver
{
    public function resolve(Order $order): ?OrderCancellationDetails
    {
        if ($order->order_status !== OrderStatus::Cancelled) {
            return null;
        }

        $history = $this->cancellationHistory($order);
        $initiatedByCustomer = $this->initiatedByCustomer($order, $history);
        $reason = trim((string) $history?->reason);

        return new OrderCancellationDetails(
            initiatedByCustomer: $initiatedByCustomer,
            title: $initiatedByCustomer
                ? 'Cancelaste este pedido'
                : 'Pedido cancelado por la tienda',
            reason: $reason !== ''
                ? $reason
                : 'No se registro un motivo para esta cancelacion.',
            occurredAt: $history?->created_at
                ?? $order->cancelled_at
                ?? $order->updated_at,
            refundMessage: match ($order->payment_status) {
                PaymentStatus::RefundPending => 'El reembolso al medio de pago original esta pendiente de confirmacion.',
                PaymentStatus::Refunded => 'El reembolso al medio de pago original fue confirmado.',
                default => null,
            },
        );
    }

    private function cancellationHistory(Order $order): ?OrderStatusHistory
    {
        $histories = $order->relationLoaded('statusHistories')
            ? $order->statusHistories
            : $order->statusHistories()->get();

        return $histories
            ->filter(fn (OrderStatusHistory $history): bool => $history->domain === OrderHistoryDomain::Order
                && $history->to_status === OrderStatus::Cancelled->value)
            ->sortByDesc('id')
            ->first();
    }

    private function initiatedByCustomer(Order $order, ?OrderStatusHistory $history): bool
    {
        if ($history === null || data_get($history->metadata, 'source') === 'admin') {
            return false;
        }

        return $history->actor_id !== null
            && $order->user_id !== null
            && (int) $history->actor_id === (int) $order->user_id;
    }
}
