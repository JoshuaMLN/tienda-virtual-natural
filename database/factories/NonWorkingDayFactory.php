<?php

namespace Database\Factories;

use App\Models\NonWorkingDay;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<NonWorkingDay> */
class NonWorkingDayFactory extends Factory
{
    public function definition(): array
    {
        return [
            'date' => fake()->unique()->dateTimeBetween('+1 day', '+1 year')->format('Y-m-d'),
            'reason' => fake()->optional()->sentence(3),
        ];
    }
}
