<?php

namespace Tests\Feature;

use App\Models\Brand;
use App\Models\Category;
use App\Models\Product;
use App\Support\Cart\CartService;
use App\Support\Cart\CartStorageInterface;
use App\Support\Cart\ProductUnavailableException;
use App\Support\Inventory\InsufficientStockException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CartServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_adds_visible_product_and_accumulates_quantity(): void
    {
        $product = $this->product(stock: 8, price: 19.90);

        $cart = $this->service()->add($product, 2);
        $cart = $this->service()->add($product->id, 3);

        $this->assertSame(1, $cart->productCount());
        $this->assertSame(5, $cart->totalQuantity());
        $this->assertSame(9950, $cart->subtotalCents());
        $this->assertSame('S/ 99.50', $cart->formattedTotal());
        $this->assertSame(5, $cart->items->first()->quantity);
    }

    public function test_rejects_product_that_is_not_visible_in_store(): void
    {
        $product = $this->product(isActive: false);

        $this->expectException(ProductUnavailableException::class);

        $this->service()->add($product, 1);
    }

    public function test_rejects_unpublished_product(): void
    {
        $product = $this->product(publishedAt: now()->addDay());

        $this->expectException(ProductUnavailableException::class);

        $this->service()->add($product, 1);
    }

    public function test_rejects_product_with_inactive_category_or_brand(): void
    {
        $inactiveCategoryProduct = $this->product(category: $this->category(isActive: false));
        $inactiveBrandProduct = $this->product(brand: $this->brand(isActive: false));

        try {
            $this->service()->add($inactiveCategoryProduct, 1);
            $this->fail('Expected unavailable product for inactive category.');
        } catch (ProductUnavailableException) {
            $this->assertTrue(true);
        }

        $this->expectException(ProductUnavailableException::class);

        $this->service()->add($inactiveBrandProduct, 1);
    }

    public function test_rejects_quantity_greater_than_available_stock(): void
    {
        $product = $this->product(stock: 2);

        $this->expectException(InsufficientStockException::class);

        $this->service()->add($product, 3);
    }

    public function test_recalculates_prices_from_current_product_data(): void
    {
        $product = $this->product(stock: 4, price: 10);

        $this->service()->add($product, 2);
        $product->update(['price' => 12.50]);

        $cart = $this->service()->get();

        $this->assertSame(2500, $cart->subtotalCents());
        $this->assertSame('S/ 25.00', $cart->formattedSubtotal());
    }

    public function test_get_adjusts_quantity_when_stock_drops(): void
    {
        $product = $this->product(stock: 5);

        $this->service()->add($product, 5);
        $product->update(['stock' => 3]);

        $cart = $this->service()->get();

        $this->assertSame(3, $cart->totalQuantity());
        $this->assertSame(3, $this->storage()->all()[$product->id]);
        $this->assertSame(
            "{$product->name}: solicitaste 5 unidades, pero solo hay 3 disponibles. Actualizamos tu carrito a 3 unidades.",
            $cart->warnings[0],
        );
    }

    public function test_get_removes_product_when_stock_is_zero(): void
    {
        $product = $this->product(stock: 2);

        $this->service()->add($product, 2);
        $product->update(['stock' => 0]);

        $cart = $this->service()->get();

        $this->assertTrue($cart->isEmpty());
        $this->assertSame([], $this->storage()->all());
        $this->assertSame(
            "{$product->name}: solicitaste 2 unidades, pero el producto ya no tiene stock disponible. Lo retiramos de tu carrito.",
            $cart->warnings[0],
        );
    }

    public function test_remove_and_clear_update_the_stored_cart(): void
    {
        $firstProduct = $this->product(name: 'Omega 3', slug: 'omega-3');
        $secondProduct = $this->product(name: 'Maca negra', slug: 'maca-negra');

        $this->service()->add($firstProduct, 1);
        $this->service()->add($secondProduct, 2);

        $cart = $this->service()->remove($firstProduct);

        $this->assertSame(1, $cart->productCount());
        $this->assertSame(2, $cart->totalQuantity());
        $this->assertArrayNotHasKey($firstProduct->id, $this->storage()->all());

        $cart = $this->service()->clear();

        $this->assertTrue($cart->isEmpty());
        $this->assertSame([], $this->storage()->all());
    }

    private function service(): CartService
    {
        return app(CartService::class);
    }

    private function storage(): CartStorageInterface
    {
        return app(CartStorageInterface::class);
    }

    private function product(
        ?Category $category = null,
        ?Brand $brand = null,
        string $name = 'Omega 3 Premium',
        ?string $slug = null,
        int $stock = 10,
        float $price = 79.90,
        bool $isActive = true,
        mixed $publishedAt = null,
    ): Product {
        $slug ??= 'producto-'.uniqid();

        return Product::query()->create([
            'category_id' => ($category ?? $this->category())->id,
            'brand_id' => $brand?->id,
            'name' => $name,
            'slug' => $slug,
            'sku' => 'SKU-'.uniqid(),
            'price' => $price,
            'stock' => $stock,
            'is_active' => $isActive,
            'published_at' => $publishedAt ?? now()->subMinute(),
        ]);
    }

    private function category(bool $isActive = true): Category
    {
        return Category::query()->create([
            'name' => 'Suplementos '.uniqid(),
            'slug' => 'suplementos-'.uniqid(),
            'is_active' => $isActive,
        ]);
    }

    private function brand(bool $isActive = true): Brand
    {
        return Brand::query()->create([
            'name' => 'Good Nature '.uniqid(),
            'slug' => 'good-nature-'.uniqid(),
            'is_active' => $isActive,
        ]);
    }
}
