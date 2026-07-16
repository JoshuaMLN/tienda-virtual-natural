<?php

namespace App\Support\Orders;

use App\Enums\DeliveryStatus;
use App\Enums\OrderStatus;
use App\Enums\PaymentStatus;
use App\Models\Order;
use DateTimeInterface;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;

class OrderCreationService
{
    public function __construct(
        private readonly OrderNumberGenerator $numbers,
        private readonly OrderHistoryRecorder $history,
        private readonly OrderInvariantValidator $invariants,
    ) {}

    /**
     * @param  array<string, mixed>  $orderAttributes
     * @param  iterable<array<string, mixed>>  $itemAttributes
     */
    public function create(array $orderAttributes, iterable $itemAttributes = [], ?DateTimeInterface $date = null): Order
    {
        $items = [];

        foreach ($itemAttributes as $attributes) {
            $items[] = $attributes;
        }

        $orderAttributes = array_merge([
            'order_status' => OrderStatus::PendingPayment,
            'payment_status' => PaymentStatus::Pending,
            'delivery_status' => DeliveryStatus::Pending,
        ], $orderAttributes);

        $this->invariants->validate($orderAttributes, $items);

        return DB::transaction(function () use ($orderAttributes, $items, $date): Order {
            $number = $this->numbers->next($date);
            $order = new Order;
            $order->fill(Arr::except($orderAttributes, ['code', 'sequence_year', 'sequence_number']));
            $order->forceFill([
                'code' => $number->code,
                'sequence_year' => $number->year,
                'sequence_number' => $number->number,
            ]);
            $order->save();

            foreach ($items as $attributes) {
                $order->items()->create(Arr::except($attributes, ['order_id']));
            }

            $this->history->recordInitialStates($order);

            return $order->load(['items', 'statusHistories']);
        }, 5);
    }
}
