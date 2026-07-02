<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\InventoryMovement;
use App\Models\Product;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class InventoryModelTest extends TestCase
{
    use RefreshDatabase;

    public function test_product_has_default_low_stock_threshold(): void
    {
        $product = Product::query()->create([
            'category_id' => $this->category()->id,
            'name' => 'Omega 3 Premium',
            'slug' => 'omega-3-premium',
            'sku' => 'VN-OMEGA-120',
            'price' => 79.90,
            'stock' => 45,
        ])->refresh();

        $this->assertSame(5, $product->low_stock_threshold);
    }

    public function test_inventory_movement_belongs_to_product(): void
    {
        $product = Product::query()->create([
            'category_id' => $this->category()->id,
            'name' => 'Maca Negra',
            'slug' => 'maca-negra',
            'sku' => 'VN-MACA-200',
            'price' => 34.90,
            'stock' => 10,
            'low_stock_threshold' => 3,
        ]);

        $movement = $product->inventoryMovements()->create([
            'type' => InventoryMovement::TYPE_IN,
            'quantity' => 5,
            'stock_before' => 10,
            'stock_after' => 15,
            'reason' => 'Reposicion inicial',
            'reference' => 'AJ-0001',
        ]);

        $this->assertTrue($movement->product->is($product));
        $this->assertTrue($product->inventoryMovements()->first()->is($movement));
        $this->assertSame(5, $movement->quantity);
        $this->assertSame(10, $movement->stock_before);
        $this->assertSame(15, $movement->stock_after);
    }

    public function test_inventory_movement_types_are_defined(): void
    {
        $this->assertSame([
            InventoryMovement::TYPE_IN,
            InventoryMovement::TYPE_OUT,
            InventoryMovement::TYPE_ADJUSTMENT,
        ], InventoryMovement::TYPES);
    }

    private function category(): Category
    {
        return Category::query()->create([
            'name' => 'Suplementos',
            'slug' => 'suplementos',
            'is_active' => true,
        ]);
    }
}
