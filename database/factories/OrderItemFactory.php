<?php

namespace Database\Factories;

use App\Enums\TaxAffectation;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Product;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<OrderItem> */
class OrderItemFactory extends Factory
{
    public function definition(): array
    {
        return [
            'order_id' => Order::factory(),
            'product_id' => Product::factory(),
            'product_sku' => 'SKU-'.fake()->unique()->numerify('######'),
            'product_name' => fake()->words(3, true),
            'product_image' => Product::DEFAULT_IMAGE,
            'product_presentation' => '120 capsulas',
            'sale_unit' => 'unidad',
            'quantity' => 1,
            'tax_affectation' => TaxAffectation::Taxed,
            'tax_rate_bps' => 1800,
            'unit_price_cents' => 10_000,
            'gross_total_cents' => 10_000,
            'discount_cents' => 0,
            'net_value_cents' => 8475,
            'tax_cents' => 1525,
            'total_cents' => 10_000,
        ];
    }

    public function exempt(): static
    {
        return $this->state(fn (): array => [
            'tax_affectation' => TaxAffectation::Exempt,
            'tax_rate_bps' => 0,
            'net_value_cents' => 10_000,
            'tax_cents' => 0,
        ]);
    }

    public function unaffected(): static
    {
        return $this->state(fn (): array => [
            'tax_affectation' => TaxAffectation::Unaffected,
            'tax_rate_bps' => 0,
            'net_value_cents' => 10_000,
            'tax_cents' => 0,
        ]);
    }
}
