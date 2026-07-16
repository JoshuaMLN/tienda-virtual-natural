<?php

namespace App\Support\Orders;

use App\Enums\OrderHistoryDomain;
use App\Models\Order;
use App\Models\OrderStatusHistory;
use App\Models\User;

class OrderHistoryRecorder
{
    public function recordInitialStates(Order $order): void
    {
        $this->record($order, OrderHistoryDomain::Order, null, $order->order_status->value);
        $this->record($order, OrderHistoryDomain::Payment, null, $order->payment_status->value);
        $this->record($order, OrderHistoryDomain::Delivery, null, $order->delivery_status->value);
    }

    /**
     * @param  array<string, mixed>  $metadata
     */
    public function record(
        Order $order,
        OrderHistoryDomain $domain,
        ?string $fromStatus,
        string $toStatus,
        ?User $actor = null,
        ?string $reason = null,
        array $metadata = [],
    ): OrderStatusHistory {
        return $order->statusHistories()->create([
            'domain' => $domain,
            'from_status' => $fromStatus,
            'to_status' => $toStatus,
            'actor_id' => $actor?->getKey(),
            'actor_name' => $actor?->name,
            'actor_email' => $actor?->email,
            'reason' => $reason,
            'metadata' => $metadata === [] ? null : $metadata,
        ]);
    }
}
