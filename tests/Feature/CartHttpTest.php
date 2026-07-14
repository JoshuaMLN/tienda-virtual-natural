<?php

namespace Tests\Feature;

use App\Models\Brand;
use App\Models\Category;
use App\Models\Product;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CartHttpTest extends TestCase
{
    use RefreshDatabase;

    public function test_cart_page_uses_controller_route(): void
    {
        $this->get(route('shop.cart'))
            ->assertOk()
            ->assertViewIs('shop.cart')
            ->assertViewHas('cart');
    }

    public function test_info_returns_empty_cart_summary(): void
    {
        $this->jsonRequest()
            ->getJson(route('shop.cart.info'))
            ->assertOk()
            ->assertJsonPath('cart.total_quantity', 0)
            ->assertJsonPath('cart.product_count', 0)
            ->assertJsonPath('cart.items', []);
    }

    public function test_store_adds_visible_product_to_session_cart(): void
    {
        $product = $this->product(stock: 6, price: 20);

        $this->jsonRequest()
            ->postJson(route('shop.cart.items.store'), [
                'product_id' => $product->id,
                'quantity' => 2,
            ])
            ->assertOk()
            ->assertJsonPath('message', 'Producto agregado al carrito.')
            ->assertJsonPath('cart.total_quantity', 2)
            ->assertJsonPath('cart.items.0.product_id', $product->id)
            ->assertJsonPath('cart.items.0.formatted_subtotal', 'S/ 40.00');
    }

    public function test_store_validation_uses_readable_messages(): void
    {
        $response = $this->jsonRequest()
            ->postJson(route('shop.cart.items.store'), []);

        $response->assertUnprocessable()
            ->assertJsonValidationErrors(['product_id', 'quantity'])
            ->assertJsonPath('errors.quantity.0', 'Ingresa la cantidad que deseas agregar.');

        $this->assertStringNotContainsString('validation.required', $response->getContent());
    }

    public function test_store_rejects_product_that_is_not_visible(): void
    {
        $product = $this->product(isActive: false);

        $this->jsonRequest()
            ->postJson(route('shop.cart.items.store'), [
                'product_id' => $product->id,
                'quantity' => 1,
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['product_id'])
            ->assertJsonPath('errors.product_id.0', 'Este producto no esta disponible en la tienda.');
    }

    public function test_store_rejects_quantity_greater_than_stock(): void
    {
        $product = $this->product(stock: 1);

        $this->jsonRequest()
            ->postJson(route('shop.cart.items.store'), [
                'product_id' => $product->id,
                'quantity' => 2,
            ])
            ->assertUnprocessable()
            ->assertJsonPath('message', 'No hay stock suficiente. Stock disponible: 1.')
            ->assertJsonValidationErrors(['quantity']);
    }

    public function test_update_changes_quantity(): void
    {
        $product = $this->product(stock: 8);

        $this->jsonRequest()
            ->postJson(route('shop.cart.items.store'), [
                'product_id' => $product->id,
                'quantity' => 1,
            ])
            ->assertOk();

        $this->jsonRequest()
            ->patchJson(route('shop.cart.items.update', $product), [
                'quantity' => 5,
            ])
            ->assertOk()
            ->assertJsonPath('message', 'Carrito actualizado.')
            ->assertJsonPath('cart.total_quantity', 5)
            ->assertJsonPath('cart.items.0.quantity', 5);
    }

    public function test_update_rejects_quantity_greater_than_stock(): void
    {
        $product = $this->product(stock: 2);

        $this->jsonRequest()
            ->postJson(route('shop.cart.items.store'), [
                'product_id' => $product->id,
                'quantity' => 1,
            ])
            ->assertOk();

        $this->jsonRequest()
            ->patchJson(route('shop.cart.items.update', $product), [
                'quantity' => 3,
            ])
            ->assertUnprocessable()
            ->assertJsonPath('message', 'No hay stock suficiente. Stock disponible: 2.')
            ->assertJsonValidationErrors(['quantity'])
            ->assertJsonPath('cart.total_quantity', 1);
    }

    public function test_destroy_and_clear_remove_items(): void
    {
        $firstProduct = $this->product(name: 'Omega', slug: 'omega');
        $secondProduct = $this->product(name: 'Maca', slug: 'maca');

        $this->jsonRequest()->postJson(route('shop.cart.items.store'), [
            'product_id' => $firstProduct->id,
            'quantity' => 1,
        ])->assertOk();

        $this->jsonRequest()->postJson(route('shop.cart.items.store'), [
            'product_id' => $secondProduct->id,
            'quantity' => 2,
        ])->assertOk();

        $this->jsonRequest()
            ->deleteJson(route('shop.cart.items.destroy', $firstProduct))
            ->assertOk()
            ->assertJsonPath('cart.total_quantity', 2)
            ->assertJsonPath('cart.items.0.product_id', $secondProduct->id);

        $this->jsonRequest()
            ->deleteJson(route('shop.cart.clear'))
            ->assertOk()
            ->assertJsonPath('cart.total_quantity', 0)
            ->assertJsonPath('cart.items', []);
    }

    public function test_shop_layout_exposes_csrf_token_for_cart_fetch_requests(): void
    {
        $this->get(route('shop.index'))
            ->assertOk()
            ->assertSee('name="csrf-token"', false);
    }

    public function test_navbar_count_reflects_session_cart(): void
    {
        $product = $this->product(stock: 5);

        $this->jsonRequest()->postJson(route('shop.cart.items.store'), [
            'product_id' => $product->id,
            'quantity' => 3,
        ])->assertOk();

        $this->get(route('shop.catalog'))
            ->assertOk()
            ->assertSee('data-cart-count', false)
            ->assertSee('data-cart-info-url="'.route('shop.cart.info').'"', false)
            ->assertSee('<span class="cart-count" data-cart-count data-cart-info-url="'.route('shop.cart.info').'">3</span>', false);
    }

    public function test_catalog_cards_expose_add_to_cart_buttons(): void
    {
        $product = $this->product(stock: 4);

        $this->get(route('shop.catalog'))
            ->assertOk()
            ->assertSee('data-cart-add', false)
            ->assertSee('data-cart-modal-trigger', false)
            ->assertSee('data-cart-product-id="'.$product->id.'"', false)
            ->assertSee('data-cart-url="'.route('shop.cart.items.store').'"', false)
            ->assertSee('data-cart-modal-stock="4"', false)
            ->assertSee('data-cart-modal-name="'.$product->name.'"', false)
            ->assertSee('Anadir al carrito');
    }

    public function test_product_detail_exposes_quantity_aware_cart_button(): void
    {
        $product = $this->product(stock: 4, slug: 'omega-detalle');

        $this->get(route('shop.product', $product->slug))
            ->assertOk()
            ->assertSee('data-cart-form', false)
            ->assertSee('data-cart-quantity', false)
            ->assertSee('data-cart-add', false)
            ->assertSee('data-cart-product-id="'.$product->id.'"', false)
            ->assertSee('data-cart-url="'.route('shop.cart.items.store').'"', false);
    }

    public function test_shop_layout_includes_cart_drawer_preview(): void
    {
        $this->get(route('shop.index'))
            ->assertOk()
            ->assertSee('id="cartDrawer"', false)
            ->assertSee('data-cart-drawer', false)
            ->assertSee('data-cart-drawer-items', false)
            ->assertSee(route('shop.cart'), false)
            ->assertSee(route('checkout.index'), false);
    }

    public function test_navbar_cart_button_opens_drawer_instead_of_direct_navigation(): void
    {
        $this->get(route('shop.catalog'))
            ->assertOk()
            ->assertSee('data-bs-target="#cartDrawer"', false)
            ->assertSee('aria-controls="cartDrawer"', false)
            ->assertSee('id="cartQuantityModal"', false)
            ->assertSee('data-cart-quantity-modal', false)
            ->assertSee('data-cart-modal-submit', false)
            ->assertDontSee('<a class="header-action" href="'.route('shop.cart').'" aria-label="Carrito">', false);
    }

    public function test_cart_page_displays_empty_state(): void
    {
        $this->get(route('shop.cart'))
            ->assertOk()
            ->assertSee('Tu carrito esta vacio')
            ->assertSee('data-cart-empty', false)
            ->assertDontSee('Omega 3 Premium');
    }

    public function test_cart_page_displays_real_session_items_and_actions(): void
    {
        $product = $this->product(stock: 7, price: 25.50, slug: 'omega-real');

        $this->jsonRequest()->postJson(route('shop.cart.items.store'), [
            'product_id' => $product->id,
            'quantity' => 2,
        ])->assertOk();

        $this->get(route('shop.cart'))
            ->assertOk()
            ->assertSee($product->name)
            ->assertSee('S/ 25.50')
            ->assertSee('S/ 51.00')
            ->assertSee('1 (2 unidades)')
            ->assertSee('value="2"', false)
            ->assertSee('data-cart-page-quantity', false)
            ->assertSee('data-cart-update-url="'.route('shop.cart.items.update', $product).'"', false)
            ->assertSee('data-cart-remove-url="'.route('shop.cart.items.destroy', $product).'"', false)
            ->assertSee('data-cart-summary-total', false)
            ->assertSee('id="clearCartModal"', false)
            ->assertSee('data-bs-target="#clearCartModal"', false)
            ->assertSee('data-cart-clear', false)
            ->assertDontSee('Maca negra en polvo');
    }

    public function test_cart_stock_warnings_persist_until_user_clears_them(): void
    {
        $product = $this->product(stock: 5);

        $this->jsonRequest()->postJson(route('shop.cart.items.store'), [
            'product_id' => $product->id,
            'quantity' => 4,
        ])->assertOk();

        $product->update(['stock' => 2]);

        $warning = "{$product->name}: solicitaste 4 unidades, pero solo hay 2 disponibles. Actualizamos tu carrito a 2 unidades.";

        $this->jsonRequest()
            ->getJson(route('shop.cart.info'))
            ->assertOk()
            ->assertJsonPath('cart.total_quantity', 2)
            ->assertJsonPath('warnings.0', $warning);

        $this->jsonRequest()
            ->getJson(route('shop.cart.info'))
            ->assertOk()
            ->assertJsonPath('warnings.0', $warning);

        $this->get(route('shop.cart'))
            ->assertOk()
            ->assertSee($warning)
            ->assertSee('data-cart-warnings-clear', false)
            ->assertSee('data-cart-warnings-clear-url="'.route('shop.cart.warnings.clear').'"', false);

        $this->jsonRequest()
            ->deleteJson(route('shop.cart.warnings.clear'))
            ->assertOk()
            ->assertJsonPath('warnings', []);

        $this->jsonRequest()
            ->getJson(route('shop.cart.info'))
            ->assertOk()
            ->assertJsonPath('warnings', []);
    }

    private function jsonRequest(): self
    {
        return $this->withHeaders([
            'Accept' => 'application/json',
        ]);
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
}
