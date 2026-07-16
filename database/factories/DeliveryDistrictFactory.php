<?php

namespace Database\Factories;

use App\Models\DeliveryDistrict;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<DeliveryDistrict>
 */
class DeliveryDistrictFactory extends Factory
{
    public function definition(): array
    {
        return [
            'ubigeo' => fake()->unique()->numerify('15####'),
            'province_code' => '1501',
            'department' => 'Lima',
            'province' => 'Lima',
            'district' => fake()->unique()->city(),
            'shipping_fee' => fake()->randomElement(['10.00', '12.00', '15.00']),
            'is_active' => true,
        ];
    }

    public function inactive(): static
    {
        return $this->state(fn (): array => ['is_active' => false]);
    }
}
