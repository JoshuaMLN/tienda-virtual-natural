<?php

namespace Tests\Feature;

use App\Models\Cart as CartModel;
use App\Models\CartItem;
use App\Models\Product;
use App\Models\User;
use App\Support\Cart\CartService;
use App\Support\Cart\CartStorageInterface;
use App\Support\Cart\DatabaseCartStorage;
use App\Support\Cart\SessionCartStorage;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Auth;
use Tests\TestCase;

class CartPersistenceTest extends TestCase
{
    use RefreshDatabase;

    public function test_cart_factories_and_relationships_are_connected(): void
    {
        $item = CartItem::factory()->create([
            'quantity' => 3,
            'price_reference' => '79.90',
        ]);

        $this->assertInstanceOf(User::class, $item->cart->user);
        $this->assertInstanceOf(Product::class, $item->product);
        $this->assertTrue($item->cart->user->cart->is($item->cart));
        $this->assertTrue($item->product->cartItems->first()->is($item));
        $this->assertSame(3, $item->quantity);
        $this->assertSame('79.90', $item->price_reference);
    }

    public function test_user_has_only_one_cart_and_product_is_unique_inside_it(): void
    {
        $user = User::factory()->create();
        $cart = CartModel::factory()->for($user)->create();
        $product = Product::factory()->create();
        CartItem::factory()->for($cart)->for($product)->create();

        try {
            CartModel::factory()->for($user)->create();
            $this->fail('Se esperaba la restriccion unica por usuario.');
        } catch (QueryException) {
            $this->assertDatabaseCount('carts', 1);
        }

        $this->expectException(QueryException::class);
        CartItem::factory()->for($cart)->for($product)->create();
    }

    public function test_deleting_user_or_product_cascades_cart_records(): void
    {
        $user = User::factory()->create();
        $cart = CartModel::factory()->for($user)->create();
        $product = Product::factory()->create();
        CartItem::factory()->for($cart)->for($product)->create();

        $product->delete();
        $this->assertDatabaseCount('cart_items', 0);

        CartItem::factory()->for($cart)->create();
        $user->delete();

        $this->assertDatabaseCount('carts', 0);
        $this->assertDatabaseCount('cart_items', 0);
    }

    public function test_storage_resolver_uses_session_for_guest_and_database_for_customer(): void
    {
        $this->assertInstanceOf(
            SessionCartStorage::class,
            app(CartStorageInterface::class)
        );

        $this->actingAs(User::factory()->create());

        $this->assertInstanceOf(
            DatabaseCartStorage::class,
            app(CartStorageInterface::class)
        );
    }

    public function test_authenticated_cart_persists_and_is_isolated_by_customer(): void
    {
        $product = Product::factory()->create(['price' => '25.00', 'stock' => 20]);
        $firstUser = User::factory()->create();
        $secondUser = User::factory()->create();

        $this->actingAs($firstUser);
        app(CartService::class)->add($product, 4);

        $this->assertDatabaseHas('cart_items', [
            'cart_id' => $firstUser->cart()->firstOrFail()->id,
            'product_id' => $product->id,
            'quantity' => 4,
            'price_reference' => '25.00',
        ]);

        Auth::logout();
        $this->actingAs($secondUser);
        $this->assertSame(0, app(CartService::class)->count());

        Auth::logout();
        $this->actingAs($firstUser);
        $this->assertSame(4, app(CartService::class)->count());
    }

    public function test_authenticated_customer_can_update_remove_and_clear_database_cart(): void
    {
        $user = User::factory()->create();
        $firstProduct = Product::factory()->create(['stock' => 20]);
        $secondProduct = Product::factory()->create(['stock' => 20]);
        $this->actingAs($user);
        $service = app(CartService::class);

        $service->add($firstProduct, 2);
        $service->add($secondProduct, 3);
        $service->update($firstProduct, 5);
        $service->remove($secondProduct);

        $this->assertDatabaseHas('cart_items', [
            'product_id' => $firstProduct->id,
            'quantity' => 5,
        ]);
        $this->assertDatabaseMissing('cart_items', [
            'product_id' => $secondProduct->id,
        ]);

        $service->clear();
        $this->assertDatabaseCount('cart_items', 0);
    }

    public function test_price_change_is_reported_once_and_reference_is_refreshed(): void
    {
        $user = User::factory()->create();
        $product = Product::factory()->create([
            'name' => 'Omega 3 Premium',
            'price' => '84.90',
        ]);
        $cart = CartModel::factory()->for($user)->create();
        $item = CartItem::factory()->for($cart)->for($product)->create([
            'quantity' => 2,
            'price_reference' => '79.90',
        ]);
        $this->actingAs($user);
        $service = app(CartService::class);

        $cartView = $service->get();

        $this->assertContains(
            'Omega 3 Premium: su precio cambio de S/ 79.90 a S/ 84.90. Actualizamos el precio de tu carrito.',
            $cartView->warnings
        );
        $this->assertSame('84.90', $item->fresh()->price_reference);

        $service->clearWarnings();
        $this->assertSame([], $service->get()->warnings);
    }

    public function test_guest_cart_also_reports_price_changes_without_freezing_price(): void
    {
        $product = Product::factory()->create([
            'name' => 'Maca Negra',
            'price' => '34.90',
            'stock' => 10,
        ]);
        $service = app(CartService::class);
        $service->add($product, 2);

        $product->update(['price' => '39.90']);
        $cartView = $service->get();

        $this->assertContains(
            'Maca Negra: su precio cambio de S/ 34.90 a S/ 39.90. Actualizamos el precio de tu carrito.',
            $cartView->warnings
        );
        $this->assertSame('S/ 79.80', $cartView->formattedTotal());
        $this->assertSame(
            '39.90',
            app(SessionCartStorage::class)->priceReferences()[$product->id]
        );

        $service->clearWarnings();
        $this->assertSame([], $service->get()->warnings);
    }
}
