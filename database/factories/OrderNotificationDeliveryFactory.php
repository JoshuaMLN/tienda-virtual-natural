<?php

namespace Database\Factories;

use App\Enums\OrderNotificationStatus;
use App\Enums\OrderNotificationType;
use App\Models\Order;
use App\Models\OrderNotificationDelivery;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<OrderNotificationDelivery> */
class OrderNotificationDeliveryFactory extends Factory
{
    public function definition(): array
    {
        return [
            'order_id' => Order::factory(),
            'type' => OrderNotificationType::Created,
            'recipient_email' => mb_strtolower(fake()->unique()->safeEmail()),
            'recipient_name' => fake()->name(),
            'status' => OrderNotificationStatus::Queued,
            'attempts' => 0,
            'queued_at' => now(),
            'last_attempt_at' => null,
            'sent_at' => null,
            'failed_at' => null,
            'last_error' => null,
        ];
    }

    public function sent(): static
    {
        return $this->state(fn (): array => [
            'status' => OrderNotificationStatus::Sent,
            'attempts' => 1,
            'last_attempt_at' => now(),
            'sent_at' => now(),
            'failed_at' => null,
            'last_error' => null,
        ]);
    }
}
