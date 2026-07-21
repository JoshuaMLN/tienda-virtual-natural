<?php

namespace Tests\Feature\Checkout;

use App\Enums\DeliveryStatus;
use App\Enums\OrderStatus;
use App\Enums\PaymentStatus;
use App\Enums\ReservationStatus;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Product;
use App\Models\StockReservation;
use App\Models\User;
use App\Support\Cart\CartService;
use App\Support\Orders\OrderPaymentService;
use App\Support\Orders\Reservations\StockReservationService;
use Carbon\CarbonImmutable;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;

class CheckoutPendingOrderTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        CarbonImmutable::setTestNow('2026-07-21 10:00:00');
    }

    protected function tearDown(): void
    {
        CarbonImmutable::setTestNow();

        parent::tearDown();
    }

    public function test_pending_page_shows_real_countdown_summary_and_cancellation_controls_only_to_its_owner(): void
    {
        [$owner, $order] = $this->pendingOrder(quantity: 3);
        $other = User::factory()->create();

        $this->actingAs($other)
            ->get(route('checkout.order.pending', $order->code))
            ->assertNotFound();

        $this->actingAs($owner)
            ->get(route('checkout.order.pending', $order->code))
            ->assertOk()
            ->assertHeader('Cache-Control', 'no-store, private')
            ->assertSee('data-reservation-countdown', false)
            ->assertSee('data-server-now="2026-07-21T10:00:00-05:00"', false)
            ->assertSee('data-expiration-url="'.route('checkout.order.expire', $order->code).'"', false)
            ->assertSee('1 (3 unidades)')
            ->assertSee(route('checkout.order.cancel', $order->code), false)
            ->assertSee('cancelPendingOrderModal');
    }

    public function test_cancelling_releases_stock_once_preserves_cart_and_allows_a_new_checkout(): void
    {
        [$user, $order, $product, $reservation] = $this->pendingOrder(stock: 9, quantity: 4);
        $this->actingAs($user);
        app(CartService::class)->add($product, 2);

        $this->assertTrue($user->pendingCheckoutOrder->is($order));

        $first = $this->delete(route('checkout.order.cancel', $order->code));

        $first->assertRedirect(route('shop.cart'))
            ->assertSessionHas('checkout_success');
        $this->assertSame(OrderStatus::Cancelled, $order->refresh()->order_status);
        $this->assertSame(DeliveryStatus::Cancelled, $order->delivery_status);
        $this->assertSame(PaymentStatus::Pending, $order->payment_status);
        $this->assertNull($order->pending_payment_owner_id);
        $this->assertNull($user->refresh()->pendingCheckoutOrder);
        $this->assertSame(ReservationStatus::Released, $reservation->refresh()->status);
        $this->assertSame(9, $product->refresh()->stock);
        $this->assertDatabaseHas('cart_items', [
            'product_id' => $product->id,
            'quantity' => 2,
        ]);

        $movementCount = $product->inventoryMovements()->count();
        $historyCount = $order->statusHistories()->count();

        $this->delete(route('checkout.order.cancel', $order->code))
            ->assertRedirect(route('shop.cart'))
            ->assertSessionHas('checkout_success');

        $this->assertSame($movementCount, $product->inventoryMovements()->count());
        $this->assertSame($historyCount, $order->statusHistories()->count());
        $this->assertSame(9, $product->refresh()->stock);
        $this->get(route('checkout.index'))->assertOk();
    }

    public function test_cancellation_is_private_and_rejects_an_order_whose_payment_was_confirmed(): void
    {
        [$owner, $order, $product] = $this->pendingOrder(stock: 7, quantity: 2);
        $other = User::factory()->create();

        $this->actingAs($other)
            ->delete(route('checkout.order.cancel', $order->code))
            ->assertNotFound();

        $paid = app(OrderPaymentService::class)->markPaid($order, reason: 'Pago confirmado en prueba');

        $this->assertSame(PaymentStatus::Paid, $paid->payment_status);
        $this->assertNull($paid->pending_payment_owner_id);
        $this->assertSame(5, $product->refresh()->stock);

        $this->actingAs($owner)
            ->delete(route('checkout.order.cancel', $order->code))
            ->assertRedirect(route('shop.cart'))
            ->assertSessionHas('checkout_error');

        $this->assertSame(PaymentStatus::Paid, $order->refresh()->payment_status);
        $this->assertSame(OrderStatus::PendingPayment, $order->order_status);
        $this->assertSame(5, $product->refresh()->stock);
    }

    public function test_database_rejects_two_pending_payment_slots_for_the_same_customer(): void
    {
        $user = User::factory()->create();
        Order::factory()->for($user)->create(['pending_payment_owner_id' => $user->id]);

        try {
            Order::factory()->for($user)->create(['pending_payment_owner_id' => $user->id]);
            $this->fail('La base de datos debio impedir dos pedidos pendientes para el mismo cliente.');
        } catch (QueryException) {
            $this->assertTrue(true);
        }

        $this->assertDatabaseCount('orders', 1);
    }

    public function test_pending_routes_keep_customer_verification_and_http_method_protection(): void
    {
        $pendingMiddleware = Route::getRoutes()->getByName('checkout.order.pending')?->gatherMiddleware() ?? [];
        $cancelMiddleware = Route::getRoutes()->getByName('checkout.order.cancel')?->gatherMiddleware() ?? [];
        $expirationMiddleware = Route::getRoutes()->getByName('checkout.order.expire')?->gatherMiddleware() ?? [];

        foreach ([$pendingMiddleware, $cancelMiddleware, $expirationMiddleware] as $middleware) {
            $this->assertContains('web', $middleware);
            $this->assertContains('auth', $middleware);
            $this->assertContains('customer', $middleware);
            $this->assertContains('verified', $middleware);
        }

        $route = Route::getRoutes()->getByName('checkout.order.cancel');
        $this->assertSame(['DELETE'], $route?->methods());
    }

    /** @return array{User, Order, Product, StockReservation} */
    private function pendingOrder(int $stock = 10, int $quantity = 1): array
    {
        $user = User::factory()->create();
        $product = Product::factory()->create(['stock' => $stock]);
        $expiresAt = CarbonImmutable::now()->addMinutes(20);
        $order = Order::factory()->for($user)->create([
            'pending_payment_owner_id' => $user->id,
            'reservation_expires_at' => $expiresAt,
        ]);
        $item = OrderItem::factory()->for($order)->for($product)->create([
            'quantity' => $quantity,
            'gross_total_cents' => 10_000 * $quantity,
            'net_value_cents' => 8475 * $quantity,
            'tax_cents' => 1525 * $quantity,
            'total_cents' => 10_000 * $quantity,
        ]);
        $reservation = app(StockReservationService::class)->reserve($item, $expiresAt);

        return [$user, $order, $product, $reservation];
    }
}
