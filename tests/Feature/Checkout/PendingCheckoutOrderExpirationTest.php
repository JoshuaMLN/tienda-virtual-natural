<?php

namespace Tests\Feature\Checkout;

use App\Enums\OrderStatus;
use App\Enums\PaymentStatus;
use App\Enums\ReservationStatus;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Product;
use App\Models\StockReservation;
use App\Models\User;
use App\Support\Cart\CartService;
use App\Support\Checkout\PendingCheckoutOrderExpirationService;
use App\Support\Orders\Reservations\StockReservationService;
use Carbon\CarbonImmutable;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;

class PendingCheckoutOrderExpirationTest extends TestCase
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

    public function test_due_order_expires_and_restores_stock_exactly_once(): void
    {
        [$user, $order, $product, $reservation] = $this->pendingOrder(stock: 8, quantity: 3);
        $expiresAt = $order->reservation_expires_at;
        CarbonImmutable::setTestNow($expiresAt);
        $expirations = app(PendingCheckoutOrderExpirationService::class);

        $expired = $expirations->expireIfDue($order);

        $this->assertSame(OrderStatus::Expired, $expired->order_status);
        $this->assertSame(PaymentStatus::Expired, $expired->payment_status);
        $this->assertNull($expired->pending_payment_owner_id);
        $this->assertNull($user->refresh()->pendingCheckoutOrder);
        $this->assertSame(ReservationStatus::Expired, $reservation->refresh()->status);
        $this->assertSame('Vencimiento de la reserva', $reservation->release_reason);
        $this->assertSame(8, $product->refresh()->stock);
        $this->assertSame($expiresAt->format('Y-m-d H:i:s'), $expired->reservation_expires_at->format('Y-m-d H:i:s'));

        $movementCount = $product->inventoryMovements()->count();
        $historyCount = $order->statusHistories()->count();
        $expiredAgain = $expirations->expireIfDue($expired);

        $this->assertSame(OrderStatus::Expired, $expiredAgain->order_status);
        $this->assertSame($movementCount, $product->inventoryMovements()->count());
        $this->assertSame($historyCount, $order->statusHistories()->count());
        $this->assertSame(8, $product->refresh()->stock);
    }

    public function test_future_reservation_is_not_expired_or_extended_by_reconciliation(): void
    {
        [, $order, $product, $reservation] = $this->pendingOrder(stock: 6, quantity: 2);
        $expiresAt = $order->reservation_expires_at->toImmutable();
        CarbonImmutable::setTestNow($expiresAt->subSecond());

        $result = app(PendingCheckoutOrderExpirationService::class)->expireIfDue($order);

        $this->assertNull($result);
        $this->assertSame(OrderStatus::PendingPayment, $order->refresh()->order_status);
        $this->assertSame(PaymentStatus::Pending, $order->payment_status);
        $this->assertSame(ReservationStatus::Active, $reservation->refresh()->status);
        $this->assertSame(4, $product->refresh()->stock);
        $this->assertSame($expiresAt->format('Y-m-d H:i:s'), $order->reservation_expires_at->format('Y-m-d H:i:s'));
    }

    public function test_command_processes_due_orders_in_batches_and_skips_future_reservations(): void
    {
        [, $first] = $this->pendingOrder(expiresInMinutes: 5);
        [, $second] = $this->pendingOrder(expiresInMinutes: 5);
        [, $future, $futureProduct, $futureReservation] = $this->pendingOrder(stock: 7, quantity: 2, expiresInMinutes: 20);
        CarbonImmutable::setTestNow('2026-07-21 10:06:00');

        $this->artisan('orders:expire-pending', ['--batch' => 1])
            ->expectsOutput('Pedidos vencidos o reconciliados: 1.')
            ->assertSuccessful();

        $this->assertSame(1, Order::query()->where('order_status', OrderStatus::Expired->value)->count());

        $this->artisan('orders:expire-pending')
            ->expectsOutput('Pedidos vencidos o reconciliados: 1.')
            ->assertSuccessful();
        $this->artisan('orders:expire-pending')
            ->expectsOutput('Pedidos vencidos o reconciliados: 0.')
            ->assertSuccessful();

        $this->assertSame(OrderStatus::Expired, $first->refresh()->order_status);
        $this->assertSame(OrderStatus::Expired, $second->refresh()->order_status);
        $this->assertSame(OrderStatus::PendingPayment, $future->refresh()->order_status);
        $this->assertSame(ReservationStatus::Active, $futureReservation->refresh()->status);
        $this->assertSame(5, $futureProduct->refresh()->stock);
    }

    public function test_command_validates_batch_and_is_scheduled_each_minute_without_overlap(): void
    {
        $this->artisan('orders:expire-pending', ['--batch' => 0])
            ->expectsOutput('La opcion --batch debe ser un entero entre 1 y 1000.')
            ->assertExitCode(2);

        $event = collect(app(Schedule::class)->events())
            ->first(fn ($event): bool => str_contains($event->command ?? '', 'orders:expire-pending'));

        $this->assertNotNull($event);
        $this->assertSame('* * * * *', $event->expression);
        $this->assertTrue($event->withoutOverlapping);
        $this->assertSame(5, $event->expiresAt);
    }

    public function test_checkout_reconciles_an_expired_order_before_allowing_a_new_attempt(): void
    {
        [$user, $order, $product, $reservation] = $this->pendingOrder(stock: 8, quantity: 2);
        $this->actingAs($user);
        app(CartService::class)->add($product, 1);
        $originalExpiration = $order->reservation_expires_at->toImmutable();
        CarbonImmutable::setTestNow($originalExpiration);

        $this->get(route('checkout.index'))
            ->assertOk()
            ->assertSee("El pedido {$order->code} vencio y su reserva de stock fue liberada.");

        $this->assertSame(OrderStatus::Expired, $order->refresh()->order_status);
        $this->assertSame(PaymentStatus::Expired, $order->payment_status);
        $this->assertSame(ReservationStatus::Expired, $reservation->refresh()->status);
        $this->assertSame(8, $product->refresh()->stock);
        $this->assertSame($originalExpiration->format('Y-m-d H:i:s'), $order->reservation_expires_at->format('Y-m-d H:i:s'));
    }

    public function test_expiration_endpoint_uses_server_time_is_private_and_remains_idempotent(): void
    {
        [$owner, $order, $product, $reservation] = $this->pendingOrder(stock: 9, quantity: 4);
        $other = User::factory()->create();

        $this->actingAs($other)
            ->postJson(route('checkout.order.expire', $order->code))
            ->assertNotFound();

        $this->actingAs($owner)
            ->postJson(route('checkout.order.expire', $order->code))
            ->assertStatus(409)
            ->assertJsonPath('status', OrderStatus::PendingPayment->value)
            ->assertJsonPath('server_now', '2026-07-21T10:00:00-05:00');

        $this->assertSame(5, $product->refresh()->stock);
        $this->assertSame(ReservationStatus::Active, $reservation->refresh()->status);
        CarbonImmutable::setTestNow($order->reservation_expires_at);

        $this->postJson(route('checkout.order.expire', $order->code))
            ->assertOk()
            ->assertJsonPath('status', OrderStatus::Expired->value)
            ->assertJsonPath('redirect_url', route('shop.cart'))
            ->assertSessionHas('checkout_notice');

        $movementCount = $product->inventoryMovements()->count();
        $historyCount = $order->statusHistories()->count();

        $this->postJson(route('checkout.order.expire', $order->code))
            ->assertOk()
            ->assertJsonPath('status', OrderStatus::Expired->value);

        $this->assertSame(9, $product->refresh()->stock);
        $this->assertSame($movementCount, $product->inventoryMovements()->count());
        $this->assertSame($historyCount, $order->statusHistories()->count());
    }

    public function test_opening_an_expired_pending_page_reconciles_it_and_redirects_to_cart(): void
    {
        [$owner, $order, $product] = $this->pendingOrder(stock: 5, quantity: 2);
        CarbonImmutable::setTestNow($order->reservation_expires_at);

        $this->actingAs($owner)
            ->get(route('checkout.order.pending', $order->code))
            ->assertRedirect(route('shop.cart'))
            ->assertSessionHas('checkout_notice');

        $this->assertSame(OrderStatus::Expired, $order->refresh()->order_status);
        $this->assertSame(5, $product->refresh()->stock);
    }

    public function test_expiration_route_keeps_checkout_security_middlewares(): void
    {
        $middleware = Route::getRoutes()->getByName('checkout.order.expire')?->gatherMiddleware() ?? [];

        $this->assertContains('web', $middleware);
        $this->assertContains('auth', $middleware);
        $this->assertContains('customer', $middleware);
        $this->assertContains('verified', $middleware);
        $this->assertSame(['POST'], Route::getRoutes()->getByName('checkout.order.expire')?->methods());
    }

    /** @return array{User, Order, Product, StockReservation} */
    private function pendingOrder(
        int $stock = 10,
        int $quantity = 1,
        int $expiresInMinutes = 10,
    ): array {
        $user = User::factory()->create();
        $product = Product::factory()->create(['stock' => $stock]);
        $expiresAt = CarbonImmutable::now()->addMinutes($expiresInMinutes);
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
