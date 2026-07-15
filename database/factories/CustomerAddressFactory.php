<?php

namespace Database\Factories;

use App\Models\CustomerAddress;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<CustomerAddress>
 */
class CustomerAddressFactory extends Factory
{
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'label' => fake()->randomElement(['Casa', 'Trabajo', 'Familia']),
            'recipient_name' => fake()->name(),
            'phone' => '9'.fake()->numerify('########'),
            'department' => 'Lima',
            'province' => 'Lima',
            'district' => 'Santiago de Surco',
            'ubigeo' => '150140',
            'address_line' => fake()->streetAddress(),
            'reference' => fake()->optional()->sentence(5),
            'is_default' => false,
        ];
    }

    public function default(): static
    {
        return $this->state(fn (array $attributes): array => [
            'is_default' => true,
        ]);
    }

    public function callao(): static
    {
        return $this->state(fn (array $attributes): array => [
            'department' => 'Callao',
            'province' => 'Callao',
            'district' => 'La Perla',
            'ubigeo' => '070104',
        ]);
    }
}
