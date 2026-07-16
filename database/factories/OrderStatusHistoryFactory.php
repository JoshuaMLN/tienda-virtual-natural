<?php

namespace Database\Factories;

use App\Enums\OrderHistoryDomain;
use App\Enums\OrderStatus;
use App\Models\Order;
use App\Models\OrderStatusHistory;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<OrderStatusHistory> */
class OrderStatusHistoryFactory extends Factory
{
    public function definition(): array
    {
        return [
            'order_id' => Order::factory(),
            'domain' => OrderHistoryDomain::Order,
            'from_status' => null,
            'to_status' => OrderStatus::PendingPayment->value,
            'actor_id' => null,
            'actor_name' => null,
            'actor_email' => null,
            'reason' => null,
            'metadata' => null,
        ];
    }
}
