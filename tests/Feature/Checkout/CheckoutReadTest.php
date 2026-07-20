<?php

namespace Tests\Feature\Checkout;

use App\Enums\TaxAffectation;
use App\Models\Product;
use App\Models\User;
use App\Support\Cart\CartService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CheckoutReadTest extends TestCase
{
    use RefreshDatabase;

    public function test_checkout_requires_an_authenticated_verified_customer(): void
    {
        $this->get(route('checkout.index'))
            ->assertRedirect(route('login'));

        $this->actingAs(User::factory()->unverified()->create())
            ->get(route('checkout.index'))
            ->assertRedirect(route('verification.notice'));

        $this->actingAs(User::factory()->admin()->create())
            ->get(route('checkout.index'))
            ->assertForbidden();
    }

    public function test_empty_cart_redirects_with_a_warning_that_persists_until_closed(): void
    {
        $warning = 'Tu carrito esta vacio. Agrega al menos un producto disponible antes de continuar con el checkout.';

        $this->actingAs(User::factory()->create())
            ->get(route('checkout.index'))
            ->assertRedirect(route('shop.cart'))
            ->assertSessionHas('shop.cart.warnings', fn (array $warnings): bool => $warnings === [$warning]);

        $this->get(route('shop.cart'))
            ->assertOk()
            ->assertSee($warning)
            ->assertSee('data-cart-warnings-clear', false);

        $this->get(route('shop.cart'))
            ->assertOk()
            ->assertSee($warning);

        $this->assertDatabaseCount('carts', 0);
        $this->assertDatabaseCount('orders', 0);
    }

    public function test_checkout_displays_the_real_cart_and_mixed_tax_breakdown(): void
    {
        $customer = User::factory()->create();
        $taxed = Product::factory()->create([
            'name' => 'Producto gravado',
            'price' => '118.00',
            'tax_affectation' => TaxAffectation::Taxed,
            'stock' => 10,
        ]);
        $exempt = Product::factory()->create([
            'name' => 'Producto exonerado',
            'price' => '20.00',
            'tax_affectation' => TaxAffectation::Exempt,
            'stock' => 10,
        ]);
        $unaffected = Product::factory()->create([
            'name' => 'Producto inafecto',
            'price' => '10.00',
            'tax_affectation' => TaxAffectation::Unaffected,
            'stock' => 10,
        ]);

        $this->actingAs($customer);
        $cart = app(CartService::class);
        $cart->add($taxed, 2);
        $cart->add($exempt, 1);
        $cart->add($unaffected, 3);

        $response = $this->get(route('checkout.index'))
            ->assertOk()
            ->assertViewIs('checkout.index')
            ->assertViewHas('checkout', function (array $checkout): bool {
                return $checkout['product_count'] === 3
                    && $checkout['total_quantity'] === 6
                    && $checkout['amounts']['products_subtotal_cents'] === 28600
                    && $checkout['amounts']['taxable_value_cents'] === 20000
                    && $checkout['amounts']['exempt_value_cents'] === 2000
                    && $checkout['amounts']['unaffected_value_cents'] === 3000
                    && $checkout['amounts']['tax_cents'] === 3600
                    && $checkout['amounts']['total_cents'] === 28600;
            })
            ->assertSee('Producto gravado')
            ->assertSee('Producto exonerado')
            ->assertSee('Producto inafecto')
            ->assertSee('2 x S/ 118.00')
            ->assertSee('S/ 286.00')
            ->assertSee('S/ 200.00')
            ->assertSee('S/ 36.00')
            ->assertSee('S/ 20.00')
            ->assertSee('S/ 30.00')
            ->assertSee('class="checkout-progress"', false)
            ->assertSee('class="checkout-sidebar"', false)
            ->assertSee('data-checkout-overview open', false)
            ->assertSee('Resumen y productos')
            ->assertSee('data-checkout-total', false)
            ->assertDontSee('Pagar con Culqi')
            ->assertDontSee('Culqi procesa la transaccion');

        $content = $response->getContent();
        $this->assertSame(1, substr_count($content, 'data-checkout-items'));
        $this->assertLessThan(
            strpos($content, 'id="checkout-products-title"'),
            strpos($content, 'id="checkout-summary-title"'),
        );
    }

    public function test_checkout_synchronizes_current_stock_and_price_before_rendering(): void
    {
        $customer = User::factory()->create();
        $product = Product::factory()->create([
            'name' => 'Omega actualizado',
            'price' => '79.90',
            'stock' => 5,
        ]);

        $this->actingAs($customer);
        app(CartService::class)->add($product, 4);

        $product->update([
            'price' => '84.90',
            'stock' => 2,
        ]);

        $this->get(route('checkout.index'))
            ->assertOk()
            ->assertSee('2 x S/ 84.90')
            ->assertSee('S/ 169.80')
            ->assertSee('Omega actualizado: solicitaste 4 unidades, pero solo hay 2 disponibles.')
            ->assertSee('Omega actualizado: su precio cambio de S/ 79.90 a S/ 84.90.');

        $this->assertDatabaseHas('cart_items', [
            'product_id' => $product->id,
            'quantity' => 2,
            'price_reference' => '84.90',
        ]);
        $this->assertSame(2, $product->fresh()->stock);
    }

    public function test_checkout_removes_a_product_that_is_no_longer_visible_and_redirects(): void
    {
        $customer = User::factory()->create();
        $product = Product::factory()->create(['stock' => 5]);

        $this->actingAs($customer);
        app(CartService::class)->add($product, 1);
        $product->update(['is_active' => false]);

        $this->get(route('checkout.index'))
            ->assertRedirect(route('shop.cart'))
            ->assertSessionHas('shop.cart.warnings', function (array $warnings): bool {
                return in_array('Un producto fue retirado del carrito porque ya no esta disponible.', $warnings, true)
                    && in_array('Tu carrito esta vacio. Agrega al menos un producto disponible antes de continuar con el checkout.', $warnings, true);
            });

        $this->assertDatabaseMissing('cart_items', [
            'product_id' => $product->id,
        ]);
        $this->assertDatabaseCount('orders', 0);
    }

    public function test_checkout_get_does_not_create_domain_records_or_change_inventory(): void
    {
        $customer = User::factory()->create();
        $product = Product::factory()->create([
            'price' => '59.90',
            'stock' => 8,
        ]);

        $this->actingAs($customer);
        app(CartService::class)->add($product, 2);

        $this->get(route('checkout.index'))
            ->assertOk();

        $this->assertDatabaseCount('customer_addresses', 0);
        $this->assertDatabaseCount('order_sequences', 0);
        $this->assertDatabaseCount('orders', 0);
        $this->assertDatabaseCount('order_items', 0);
        $this->assertDatabaseCount('order_status_histories', 0);
        $this->assertDatabaseCount('stock_reservations', 0);
        $this->assertDatabaseCount('inventory_movements', 0);
        $this->assertDatabaseCount('fiscal_documents', 0);
        $this->assertDatabaseCount('fiscal_document_deliveries', 0);
        $this->assertDatabaseCount('cart_items', 1);
        $this->assertSame(8, $product->fresh()->stock);
    }
}
