<?php

namespace Database\Factories;

use App\Enums\TaxAffectation;
use App\Models\Category;
use App\Models\Product;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Product>
 */
class ProductFactory extends Factory
{
    public function definition(): array
    {
        $name = fake()->unique()->words(3, true);

        return [
            'category_id' => Category::factory(),
            'brand_id' => null,
            'name' => Str::title($name),
            'slug' => Str::slug($name).'-'.fake()->unique()->numberBetween(1000, 9999),
            'sku' => 'VN-'.fake()->unique()->numerify('######'),
            'short_description' => fake()->sentence(),
            'description' => fake()->paragraph(),
            'price' => fake()->randomFloat(2, 10, 200),
            'tax_affectation' => TaxAffectation::Taxed,
            'stock' => fake()->numberBetween(5, 50),
            'is_active' => true,
            'is_featured' => false,
            'published_at' => now()->subDay(),
        ];
    }

    public function unpublished(): static
    {
        return $this->state(fn (): array => ['published_at' => null]);
    }

    public function inactive(): static
    {
        return $this->state(fn (): array => ['is_active' => false]);
    }

    public function outOfStock(): static
    {
        return $this->state(fn (): array => ['stock' => 0]);
    }
}
