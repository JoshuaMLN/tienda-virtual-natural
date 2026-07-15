<?php

namespace Tests\Feature;

use App\Models\Cart as CartModel;
use App\Models\CartItem;
use App\Models\Product;
use App\Models\User;
use App\Support\Cart\CartMergeCoordinator;
use App\Support\Cart\CartMergeService;
use App\Support\Cart\CartService;
use App\Support\Cart\SessionCartStorage;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Mockery;
use RuntimeException;
use Tests\TestCase;

class CartMergeTest extends TestCase
{
    use RefreshDatabase;

    public function test_authenticated_storage_automatically_merges_guest_and_saved_quantities(): void
    {
        $user = User::factory()->create();
        $product = Product::factory()->create(['price' => '40.00', 'stock' => 10]);
        $cart = CartModel::factory()->for($user)->create();
        CartItem::factory()->for($cart)->for($product)->create([
            'quantity' => 3,
            'price_reference' => '40.00',
        ]);
        $sessionStorage = app(SessionCartStorage::class);
        $sessionStorage->set($product->id, 2);

        $this->actingAs($user);
        $cartView = app(CartService::class)->get();

        $this->assertSame(5, $cartView->totalQuantity());
        $this->assertSame(5, $cart->items()->firstOrFail()->quantity);
        $this->assertSame([], $sessionStorage->all());
        $this->assertNotNull($cart->fresh()->last_merged_session_token);
    }

    public function test_merge_caps_combined_quantity_to_stock_and_adds_warning(): void
    {
        $user = User::factory()->create();
        $product = Product::factory()->create([
            'name' => 'Magnesio Citrato',
            'price' => '49.90',
            'stock' => 5,
        ]);
        $cart = CartModel::factory()->for($user)->create();
        CartItem::factory()->for($cart)->for($product)->create([
            'quantity' => 4,
            'price_reference' => '49.90',
        ]);
        $sessionStorage = app(SessionCartStorage::class);
        $sessionStorage->set($product->id, 3);

        app(CartMergeService::class)->merge($user);

        $this->assertSame(5, $cart->items()->firstOrFail()->quantity);
        $this->assertContains(
            'Magnesio Citrato: solicitaste 7 unidades entre tus carritos, pero solo hay 5 disponibles. Actualizamos tu carrito a 5 unidades.',
            $sessionStorage->warnings()
        );
    }

    public function test_merge_removes_unavailable_and_out_of_stock_products(): void
    {
        $user = User::factory()->create();
        $inactive = Product::factory()->inactive()->create(['name' => 'Producto oculto']);
        $outOfStock = Product::factory()->outOfStock()->create(['name' => 'Producto agotado']);
        $cart = CartModel::factory()->for($user)->create();
        CartItem::factory()->for($cart)->for($inactive)->create(['quantity' => 1]);
        CartItem::factory()->for($cart)->for($outOfStock)->create(['quantity' => 2]);
        $sessionStorage = app(SessionCartStorage::class);
        $sessionStorage->set($inactive->id, 2);
        $sessionStorage->set($outOfStock->id, 3);

        app(CartMergeService::class)->merge($user);

        $this->assertDatabaseMissing('cart_items', ['cart_id' => $cart->id]);
        $this->assertTrue(collect($sessionStorage->warnings())->contains(
            fn (string $warning): bool => str_contains($warning, 'Producto oculto')
        ));
        $this->assertTrue(collect($sessionStorage->warnings())->contains(
            fn (string $warning): bool => str_contains($warning, 'Producto agotado')
        ));
    }

    public function test_merge_detects_price_change_in_existing_item(): void
    {
        $user = User::factory()->create();
        $product = Product::factory()->create([
            'name' => 'Vitamina C',
            'price' => '35.00',
        ]);
        $cart = CartModel::factory()->for($user)->create();
        $item = CartItem::factory()->for($cart)->for($product)->create([
            'quantity' => 1,
            'price_reference' => '30.00',
        ]);
        $sessionStorage = app(SessionCartStorage::class);
        $sessionStorage->set($product->id, 1);

        app(CartMergeService::class)->merge($user);

        $this->assertSame('35.00', $item->fresh()->price_reference);
        $this->assertContains(
            'Vitamina C: su precio cambio de S/ 30.00 a S/ 35.00. Actualizamos el precio de tu carrito.',
            $sessionStorage->warnings()
        );
    }

    public function test_same_session_token_is_not_merged_twice(): void
    {
        $user = User::factory()->create();
        $product = Product::factory()->create(['price' => '20.00', 'stock' => 10]);
        $sessionStorage = app(SessionCartStorage::class);
        $sessionStorage->set($product->id, 2);
        $token = $sessionStorage->token();
        $cart = CartModel::factory()->for($user)->create([
            'last_merged_session_token' => $token,
        ]);
        CartItem::factory()->for($cart)->for($product)->create([
            'quantity' => 3,
            'price_reference' => '20.00',
        ]);

        app(CartMergeService::class)->merge($user);

        $this->assertSame(3, $cart->items()->firstOrFail()->quantity);
        $this->assertSame([], $sessionStorage->all());
    }

    public function test_login_without_guest_items_does_not_change_saved_cart(): void
    {
        $user = User::factory()->create();
        $product = Product::factory()->create();
        $cart = CartModel::factory()->for($user)->create();
        $item = CartItem::factory()->for($cart)->for($product)->create([
            'quantity' => 4,
        ]);

        app(CartMergeService::class)->merge($user);

        $this->assertSame(4, $item->fresh()->quantity);
        $this->assertNull($cart->fresh()->last_merged_session_token);
    }

    public function test_failed_merge_preserves_both_carts_and_can_be_retried(): void
    {
        $user = User::factory()->create();
        $product = Product::factory()->create(['price' => '30.00', 'stock' => 10]);
        $cart = CartModel::factory()->for($user)->create();
        CartItem::factory()->for($cart)->for($product)->create([
            'quantity' => 2,
            'price_reference' => '30.00',
        ]);
        $sessionStorage = app(SessionCartStorage::class);
        $sessionStorage->set($product->id, 3);
        $failedService = Mockery::mock(CartMergeService::class);
        $failedService->shouldReceive('merge')
            ->once()
            ->andThrow(new RuntimeException('Database unavailable'));
        $failedCoordinator = new CartMergeCoordinator($failedService, $sessionStorage);

        $this->assertFalse($failedCoordinator->mergeFor($user));
        $this->assertTrue($failedCoordinator->isPending());
        $this->assertSame(2, $cart->items()->firstOrFail()->quantity);
        $this->assertSame([$product->id => 3], $sessionStorage->all());
        $this->assertContains(
            CartMergeCoordinator::FAILURE_WARNING,
            $sessionStorage->warnings()
        );

        $retryCoordinator = new CartMergeCoordinator(
            app(CartMergeService::class),
            $sessionStorage
        );

        $this->assertTrue($retryCoordinator->mergeFor($user));
        $this->assertFalse($retryCoordinator->isPending());
        $this->assertSame(5, $cart->items()->firstOrFail()->quantity);
        $this->assertSame([], $sessionStorage->all());
        $this->assertNotContains(
            CartMergeCoordinator::FAILURE_WARNING,
            $sessionStorage->warnings()
        );
    }
}
