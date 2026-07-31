<?php

namespace Database\Factories;

use App\Enums\ReservationStatus;
use App\Models\OrderItem;
use App\Models\StockReservation;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<StockReservation> */
class StockReservationFactory extends Factory
{
    public function definition(): array
    {
        return [
            'order_item_id' => OrderItem::factory(),
            'quantity' => fn (array $attributes): int => OrderItem::query()->findOrFail($attributes['order_item_id'])->quantity,
            'status' => ReservationStatus::Active,
            'expires_at' => now()->addMinutes(15),
        ];
    }

    public function forOrderItem(OrderItem $item): static
    {
        return $this->state(fn (): array => [
            'order_item_id' => $item->getKey(),
            'quantity' => $item->quantity,
        ]);
    }

    public function consumed(): static
    {
        return $this->state(fn (): array => [
            'status' => ReservationStatus::Consumed,
            'consumed_at' => now(),
        ]);
    }

    public function restocked(): static
    {
        return $this->consumed()->state(fn (): array => [
            'restocked_at' => now(),
            'restock_reason' => 'Cancelacion pagada',
        ]);
    }
}
