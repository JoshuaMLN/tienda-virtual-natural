<?php

namespace Tests\Feature\Account;

use App\Enums\DeliveryStatus;
use App\Enums\OrderNotificationType;
use App\Enums\OrderStatus;
use App\Enums\PaymentStatus;
use App\Enums\ReservationStatus;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Product;
use App\Models\Setting;
use App\Models\StockReservation;
use App\Models\User;
use App\Support\Cart\CartService;
use App\Support\Orders\CustomerOrderCapabilityResolver;
use App\Support\Orders\OrderPaymentService;
use App\Support\Orders\OrderStateTransitionService;
use App\Support\Orders\Reservations\StockReservationService;
use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;

class CustomerOrderCancellationHttpTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        CarbonImmutable::setTestNow('2026-07-22 10:00:00');
        Setting::setValue(Setting::CONTACT_WHATSAPP, '999888777');
        Setting::setValue(Setting::CONTACT_EMAIL, 'pedidos@example.test');
    }

    protected function tearDown(): void
    {
        CarbonImmutable::setTestNow();
        Setting::clearLocalCache();

        parent::tearDown();
    }

    public function test_capabilities_distinguish_pending_payment_support_and_completed_orders(): void
    {
        [$customer, $pending] = $this->pendingOrder();
        $resolver = app(CustomerOrderCapabilityResolver::class);

        $active = $resolver->resolve($pending->load('stockReservations'));

        $this->assertTrue($active->canCancel);
        $this->assertTrue($active->canContinuePayment);
        $this->assertFalse($active->shouldContactSupport);

        $failed = app(OrderStateTransitionService::class)
            ->transitionPayment($pending, PaymentStatus::Failed);
        $failedCapabilities = $resolver->resolve($failed->load('stockReservations'));

        $this->assertTrue($failedCapabilities->canCancel);
        $this->assertTrue($failedCapabilities->canContinuePayment);

        $atExpiration = $resolver->resolve(
            $failed,
            CarbonImmutable::instance($failed->reservation_expires_at),
        );

        $this->assertFalse($atExpiration->canCancel);
        $this->assertFalse($atExpiration->canContinuePayment);

        $paid = app(OrderPaymentService::class)->markPaid($failed, reason: 'Pago confirmado en prueba');
        $paidCapabilities = $resolver->resolve($paid->load('stockReservations'));

        $this->assertFalse($paidCapabilities->canCancel);
        $this->assertFalse($paidCapabilities->canContinuePayment);
        $this->assertTrue($paidCapabilities->shouldContactSupport);

        $delivered = Order::factory()->paid()->for($customer)->create([
            'order_status' => OrderStatus::Completed,
            'delivery_status' => DeliveryStatus::Delivered,
        ]);

        $this->assertFalse($resolver->resolve($delivered)->shouldContactSupport);
    }

    public function test_list_and_detail_only_offer_cancellation_for_a_current_reservation(): void
    {
        $customer = User::factory()->create();
        $paid = Order::factory()->paid()->for($customer)->create();
        $expired = Order::factory()->for($customer)->create([
            'pending_payment_owner_id' => null,
            'order_status' => OrderStatus::Expired,
            'payment_status' => PaymentStatus::Expired,
            'reservation_expires_at' => now()->subMinute(),
        ]);
        [, $cancellable] = $this->pendingOrder($customer);

        $list = $this->actingAs($customer)->get(route('account.orders'));

        $list->assertOk()
            ->assertSee(route('account.orders.cancel', $cancellable->code), false)
            ->assertDontSee(route('account.orders.cancel', $paid->code), false)
            ->assertDontSee(route('account.orders.cancel', $expired->code), false)
            ->assertDontSee('Continuar pago')
            ->assertSee('cancelOrderModal-'.$cancellable->getKey(), false);

        $this->get(route('account.orders.show', $cancellable->code))
            ->assertOk()
            ->assertSee(route('account.orders.cancel', $cancellable->code), false)
            ->assertSee('Cancelar pedido');

        $this->get(route('account.orders.show', $expired->code))
            ->assertOk()
            ->assertDontSee(route('account.orders.cancel', $expired->code), false);

        $this->get(route('account.orders.show', $paid->code))
            ->assertOk()
            ->assertDontSee(route('account.orders.cancel', $paid->code), false)
            ->assertSee('Necesitas cambiar o cancelar este pedido?')
            ->assertSee('https://wa.me/51999888777', false)
            ->assertSee('pedidos@example.test');
    }

    public function test_pending_order_without_an_active_reservation_cannot_be_cancelled(): void
    {
        $customer = User::factory()->create();
        $order = Order::factory()->for($customer)->create([
            'pending_payment_owner_id' => $customer->id,
            'reservation_expires_at' => now()->addMinutes(20),
        ]);

        $this->actingAs($customer)
            ->delete(route('account.orders.cancel', $order->code))
            ->assertRedirect(route('account.orders.show', $order->code))
            ->assertSessionHas('order_error');

        $this->assertSame(OrderStatus::PendingPayment, $order->refresh()->order_status);
        $this->assertSame(PaymentStatus::Pending, $order->payment_status);
        $this->assertNull($order->cancelled_at);
        $this->assertDatabaseCount('inventory_movements', 0);
    }

    public function test_owner_cancellation_releases_stock_once_keeps_cart_and_is_idempotent(): void
    {
        [$customer, $order, $product, $reservation] = $this->pendingOrder(stock: 12, quantity: 3);
        $cartProduct = Product::factory()->create(['stock' => 8]);
        $this->actingAs($customer);
        app(CartService::class)->add($cartProduct, 2);
        $cartId = $customer->cart()->firstOrFail()->getKey();
        $historyBefore = $order->statusHistories()->count();
        $movementsBefore = $product->inventoryMovements()->count();

        $this->delete(route('account.orders.cancel', $order->code), ['return_to' => 'list'])
            ->assertRedirect(route('account.orders'))
            ->assertSessionHas('order_success');

        $this->assertSame(OrderStatus::Cancelled, $order->refresh()->order_status);
        $this->assertSame(DeliveryStatus::Cancelled, $order->delivery_status);
        $this->assertSame(PaymentStatus::Pending, $order->payment_status);
        $this->assertNull($order->pending_payment_owner_id);
        $this->assertSame(ReservationStatus::Released, $reservation->refresh()->status);
        $this->assertSame(12, $product->refresh()->stock);
        $this->assertSame($movementsBefore + 1, $product->inventoryMovements()->count());
        $this->assertSame($historyBefore + 3, $order->statusHistories()->count());
        $this->assertDatabaseHas('order_notification_deliveries', [
            'order_id' => $order->id,
            'type' => OrderNotificationType::Cancelled->value,
        ]);
        $notificationCount = $order->notificationDeliveries()->count();
        $this->assertDatabaseHas('cart_items', [
            'cart_id' => $cartId,
            'product_id' => $cartProduct->id,
            'quantity' => 2,
        ]);
        $this->get(route('account.orders.show', $order->code))
            ->assertOk()
            ->assertSee('Pedido cancelado')
            ->assertSee('Cancelaste este pedido')
            ->assertSee('Cancelado por el cliente')
            ->assertDontSee(route('account.orders.cancel', $order->code), false);

        $movementCount = $product->inventoryMovements()->count();
        $historyCount = $order->statusHistories()->count();

        $this->delete(route('account.orders.cancel', $order->code))
            ->assertRedirect(route('account.orders.show', $order->code))
            ->assertSessionHas('order_success');

        $this->assertSame(12, $product->refresh()->stock);
        $this->assertSame($movementCount, $product->inventoryMovements()->count());
        $this->assertSame($historyCount, $order->statusHistories()->count());
        $this->assertSame($notificationCount, $order->notificationDeliveries()->count());
    }

    public function test_payment_winning_the_race_prevents_cancellation_and_keeps_stock_sold(): void
    {
        [$customer, $order, $product, $reservation] = $this->pendingOrder(stock: 7, quantity: 2);
        app(OrderPaymentService::class)->markPaid($order, reason: 'Pago confirmado antes de cancelar');
        $movementCount = $product->inventoryMovements()->count();
        $historyCount = $order->statusHistories()->count();

        $this->actingAs($customer)
            ->delete(route('account.orders.cancel', $order->code))
            ->assertRedirect(route('account.orders.show', $order->code))
            ->assertSessionHas('order_error');

        $this->assertSame(PaymentStatus::Paid, $order->refresh()->payment_status);
        $this->assertSame(OrderStatus::PendingPayment, $order->order_status);
        $this->assertSame(ReservationStatus::Consumed, $reservation->refresh()->status);
        $this->assertSame(5, $product->refresh()->stock);
        $this->assertSame($movementCount, $product->inventoryMovements()->count());
        $this->assertSame($historyCount, $order->statusHistories()->count());
    }

    public function test_expiration_winning_the_race_expires_and_releases_exactly_once(): void
    {
        [$customer, $order, $product, $reservation] = $this->pendingOrder(
            stock: 9,
            quantity: 4,
            expiresAt: now()->addMinutes(2),
        );
        CarbonImmutable::setTestNow(now()->addMinutes(3));

        $this->actingAs($customer)
            ->delete(route('account.orders.cancel', $order->code))
            ->assertRedirect(route('account.orders.show', $order->code))
            ->assertSessionHas('order_notice');

        $this->assertSame(OrderStatus::Expired, $order->refresh()->order_status);
        $this->assertSame(PaymentStatus::Expired, $order->payment_status);
        $this->assertSame(ReservationStatus::Expired, $reservation->refresh()->status);
        $this->assertSame(9, $product->refresh()->stock);
        $this->assertDatabaseHas('order_notification_deliveries', [
            'order_id' => $order->id,
            'type' => OrderNotificationType::Expired->value,
        ]);
        $notificationCount = $order->notificationDeliveries()->count();
        $movementCount = $product->inventoryMovements()->count();
        $historyCount = $order->statusHistories()->count();

        $this->delete(route('account.orders.cancel', $order->code))
            ->assertSessionHas('order_notice');

        $this->assertSame(9, $product->refresh()->stock);
        $this->assertSame($movementCount, $product->inventoryMovements()->count());
        $this->assertSame($historyCount, $order->statusHistories()->count());
        $this->assertSame($notificationCount, $order->notificationDeliveries()->count());
    }

    public function test_cancellation_is_private_and_route_is_verified_rate_limited_and_delete_only(): void
    {
        [$owner, $order] = $this->pendingOrder();
        $other = User::factory()->create();

        $this->actingAs($other)
            ->delete(route('account.orders.cancel', $order->code))
            ->assertNotFound();

        $this->assertNotSame(OrderStatus::Cancelled, $order->refresh()->order_status);

        $route = Route::getRoutes()->getByName('account.orders.cancel');
        $middleware = $route?->gatherMiddleware() ?? [];

        $this->assertSame(['DELETE'], $route?->methods());
        $this->assertContains('web', $middleware);
        $this->assertContains('auth', $middleware);
        $this->assertContains('customer', $middleware);
        $this->assertContains('verified', $middleware);
        $this->assertContains('throttle:6,1', $middleware);

        $unverified = User::factory()->unverified()->create();
        [, $unverifiedOrder] = $this->pendingOrder($unverified);

        $this->actingAs($unverified)
            ->delete(route('account.orders.cancel', $unverifiedOrder->code))
            ->assertRedirect(route('verification.notice'));

        $this->assertSame(OrderStatus::PendingPayment, $unverifiedOrder->refresh()->order_status);
        $this->assertTrue($owner->exists);
    }

    /** @return array{User, Order, Product, StockReservation} */
    private function pendingOrder(
        ?User $customer = null,
        int $stock = 10,
        int $quantity = 1,
        ?CarbonInterface $expiresAt = null,
    ): array {
        $customer ??= User::factory()->create();
        $expiresAt ??= CarbonImmutable::now()->addMinutes(20);
        $product = Product::factory()->create(['stock' => $stock]);
        $order = Order::factory()->for($customer)->create([
            'pending_payment_owner_id' => $customer->id,
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

        return [$customer, $order, $product, $reservation];
    }
}
