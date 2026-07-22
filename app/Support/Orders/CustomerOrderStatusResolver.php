<?php

namespace App\Support\Orders;

use App\Enums\CustomerOrderFilter;
use App\Enums\CustomerOrderStatus;
use App\Enums\DeliveryStatus;
use App\Enums\OrderStatus;
use App\Enums\PaymentStatus;
use App\Models\Order;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Builder;

class CustomerOrderStatusResolver
{
    public function resolve(Order $order, ?CarbonInterface $now = null): CustomerOrderStatus
    {
        $now ??= now();

        if ($this->isExpired($order, $now)) {
            return CustomerOrderStatus::Expired;
        }

        if ($order->payment_status === PaymentStatus::Refunded) {
            return CustomerOrderStatus::Refunded;
        }

        if ($order->order_status === OrderStatus::Cancelled
            || $order->delivery_status === DeliveryStatus::Cancelled) {
            return CustomerOrderStatus::Cancelled;
        }

        return match ($order->delivery_status) {
            DeliveryStatus::Delivered => CustomerOrderStatus::Delivered,
            DeliveryStatus::PickedUp => CustomerOrderStatus::PickedUp,
            DeliveryStatus::Shipped => CustomerOrderStatus::InTransit,
            DeliveryStatus::ReadyForPickup => CustomerOrderStatus::ReadyForPickup,
            DeliveryStatus::Preparing => CustomerOrderStatus::Preparing,
            default => $this->resolveBeforeFulfillment($order),
        };
    }

    public function constrain(
        Builder $query,
        CustomerOrderFilter $filter,
        ?CarbonInterface $now = null,
    ): Builder {
        $now ??= now();

        return match ($filter) {
            CustomerOrderFilter::All => $query,
            CustomerOrderFilter::Pending => $this->constrainPending($query, $now),
            CustomerOrderFilter::Preparing => $this->constrainPreparing($query, $now),
            CustomerOrderFilter::Fulfillment => $this->constrainFulfillment($query, $now),
            CustomerOrderFilter::Completed => $this->constrainCompleted($query, $now),
            CustomerOrderFilter::Closed => $this->constrainClosed($query, $now),
        };
    }

    private function resolveBeforeFulfillment(Order $order): CustomerOrderStatus
    {
        if ($order->payment_status === PaymentStatus::Paid
            || $order->order_status === OrderStatus::Processing) {
            return CustomerOrderStatus::Preparing;
        }

        if ($order->payment_status === PaymentStatus::Failed) {
            return CustomerOrderStatus::PaymentFailed;
        }

        return CustomerOrderStatus::PendingPayment;
    }

    private function isExpired(Order $order, CarbonInterface $now): bool
    {
        if ($order->order_status === OrderStatus::Expired
            || $order->payment_status === PaymentStatus::Expired) {
            return true;
        }

        return $order->order_status === OrderStatus::PendingPayment
            && in_array($order->payment_status, [PaymentStatus::Pending, PaymentStatus::Failed], true)
            && $order->reservation_expires_at?->lte($now) === true;
    }

    private function constrainPending(Builder $query, CarbonInterface $now): Builder
    {
        return $query
            ->where('order_status', OrderStatus::PendingPayment->value)
            ->whereIn('payment_status', [PaymentStatus::Pending->value, PaymentStatus::Failed->value])
            ->where('delivery_status', DeliveryStatus::Pending->value)
            ->where(function (Builder $query) use ($now): void {
                $query->whereNull('reservation_expires_at')
                    ->orWhere('reservation_expires_at', '>', $now);
            });
    }

    private function constrainPreparing(Builder $query, CarbonInterface $now): Builder
    {
        return $this->excludeClosed($query, $now)
            ->whereNotIn('delivery_status', [
                DeliveryStatus::Shipped->value,
                DeliveryStatus::ReadyForPickup->value,
                DeliveryStatus::Delivered->value,
                DeliveryStatus::PickedUp->value,
            ])
            ->where(function (Builder $query): void {
                $query->where('delivery_status', DeliveryStatus::Preparing->value)
                    ->orWhere('payment_status', PaymentStatus::Paid->value)
                    ->orWhere('order_status', OrderStatus::Processing->value);
            });
    }

    private function constrainFulfillment(Builder $query, CarbonInterface $now): Builder
    {
        return $this->excludeClosed($query, $now)
            ->whereIn('delivery_status', [
                DeliveryStatus::Shipped->value,
                DeliveryStatus::ReadyForPickup->value,
            ]);
    }

    private function constrainCompleted(Builder $query, CarbonInterface $now): Builder
    {
        return $this->excludeClosed($query, $now)
            ->where(function (Builder $query): void {
                $query->where('order_status', OrderStatus::Completed->value)
                    ->orWhereIn('delivery_status', [
                        DeliveryStatus::Delivered->value,
                        DeliveryStatus::PickedUp->value,
                    ]);
            });
    }

    private function constrainClosed(Builder $query, CarbonInterface $now): Builder
    {
        return $query->where(function (Builder $query) use ($now): void {
            $query->whereIn('order_status', [
                OrderStatus::Cancelled->value,
                OrderStatus::Expired->value,
            ])->orWhereIn('payment_status', [
                PaymentStatus::Expired->value,
                PaymentStatus::Refunded->value,
            ])->orWhere('delivery_status', DeliveryStatus::Cancelled->value)
                ->orWhere(function (Builder $query) use ($now): void {
                    $query->where('order_status', OrderStatus::PendingPayment->value)
                        ->whereIn('payment_status', [PaymentStatus::Pending->value, PaymentStatus::Failed->value])
                        ->whereNotNull('reservation_expires_at')
                        ->where('reservation_expires_at', '<=', $now);
                });
        });
    }

    private function excludeClosed(Builder $query, CarbonInterface $now): Builder
    {
        return $query
            ->whereNotIn('order_status', [OrderStatus::Cancelled->value, OrderStatus::Expired->value])
            ->whereNotIn('payment_status', [PaymentStatus::Expired->value, PaymentStatus::Refunded->value])
            ->where('delivery_status', '!=', DeliveryStatus::Cancelled->value)
            ->where(function (Builder $query) use ($now): void {
                $query->where('order_status', '!=', OrderStatus::PendingPayment->value)
                    ->orWhereNotIn('payment_status', [PaymentStatus::Pending->value, PaymentStatus::Failed->value])
                    ->orWhereNull('reservation_expires_at')
                    ->orWhere('reservation_expires_at', '>', $now);
            });
    }
}
