<?php

namespace Database\Factories;

use App\Enums\DeliveryAttemptAttribution;
use App\Enums\DeliveryAttemptResult;
use App\Enums\DeliveryStatus;
use App\Models\DeliveryAttempt;
use App\Models\Order;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/** @extends Factory<DeliveryAttempt> */
class DeliveryAttemptFactory extends Factory
{
    public function definition(): array
    {
        return [
            'order_id' => Order::factory()->processing()->state([
                'delivery_status' => DeliveryStatus::Shipped,
            ]),
            'operation_token' => (string) Str::uuid(),
            'cycle' => 1,
            'attempt_number' => 1,
            'counted_attempt_number' => 1,
            'result' => DeliveryAttemptResult::Incident,
            'attribution' => DeliveryAttemptAttribution::Customer,
            'consumes_attempt' => true,
            'responsible_name' => fake()->name(),
            'reason' => 'No se encontro a una persona que pudiera recibir el pedido.',
            'occurred_at' => now(),
            'recorded_by_id' => User::factory()->admin(),
            'recorded_by_name' => 'Administrador de tienda',
            'recorded_by_email' => 'admin@example.test',
        ];
    }

    public function delivered(): static
    {
        return $this->state(fn (): array => [
            'result' => DeliveryAttemptResult::Delivered,
            'attribution' => DeliveryAttemptAttribution::Unattributed,
            'consumes_attempt' => false,
            'counted_attempt_number' => null,
            'reason' => null,
        ]);
    }

    public function notCounted(): static
    {
        return $this->state(fn (): array => [
            'attribution' => DeliveryAttemptAttribution::Carrier,
            'consumes_attempt' => false,
            'counted_attempt_number' => null,
        ]);
    }
}
