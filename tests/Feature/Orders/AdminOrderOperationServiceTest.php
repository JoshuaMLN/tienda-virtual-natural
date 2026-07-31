<?php

namespace Tests\Feature\Orders;

use App\Enums\AdminOrderAction;
use App\Enums\DeliveryStatus;
use App\Enums\OrderHistoryDomain;
use App\Enums\OrderNotificationType;
use App\Enums\OrderStatus;
use App\Enums\PaymentStatus;
use App\Enums\ReservationStatus;
use App\Jobs\SendOrderTransactionalEmail;
use App\Models\InventoryMovement;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\OrderNotificationDelivery;
use App\Models\OrderStatusHistory;
use App\Models\Product;
use App\Models\StockReservation;
use App\Models\User;
use App\Support\Orders\AdminOrderOperationService;
use App\Support\Orders\InvalidStateTransitionException;
use App\Support\Orders\OrderStateTransitionService;
use DomainException;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class AdminOrderOperationServiceTest extends TestCase
{
    use RefreshDatabase;

    private AdminOrderOperationService $operations;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();

        $this->operations = app(AdminOrderOperationService::class);
        $this->admin = User::factory()->admin()->create();
    }

    public function test_home_delivery_flow_exposes_and_applies_only_the_next_contextual_action(): void
    {
        $order = Order::factory()->paid()->create();

        $this->assertSame(
            [AdminOrderAction::StartPreparation, AdminOrderAction::Cancel],
            $this->operations->availableActions($order),
        );

        $order = $this->operations->perform($order, AdminOrderAction::StartPreparation, $this->admin);
        $this->assertSame(OrderStatus::Processing, $order->order_status);
        $this->assertSame(DeliveryStatus::Preparing, $order->delivery_status);
        $this->assertSame(
            [AdminOrderAction::MarkShipped, AdminOrderAction::Cancel],
            $this->operations->availableActions($order),
        );

        $order = $this->operations->perform($order, AdminOrderAction::MarkShipped, $this->admin);
        $this->assertSame(DeliveryStatus::Shipped, $order->delivery_status);
        $this->assertSame(
            [AdminOrderAction::ConfirmDelivery],
            $this->operations->availableActions($order),
        );

        $order = $this->operations->perform($order, AdminOrderAction::ConfirmDelivery, $this->admin);
        $this->assertSame(OrderStatus::Completed, $order->order_status);
        $this->assertSame(DeliveryStatus::Delivered, $order->delivery_status);
        $this->assertNotNull($order->completed_at);
        $this->assertSame([], $this->operations->availableActions($order));
    }

    public function test_pickup_flow_uses_ready_and_picked_up_states(): void
    {
        $order = Order::factory()->paid()->pickup()->create();

        $order = $this->operations->perform($order, AdminOrderAction::StartPreparation, $this->admin);
        $this->assertContains(AdminOrderAction::MarkReadyForPickup, $this->operations->availableActions($order));
        $this->assertNotContains(AdminOrderAction::MarkShipped, $this->operations->availableActions($order));

        $order = $this->operations->perform($order, AdminOrderAction::MarkReadyForPickup, $this->admin);
        $this->assertSame(DeliveryStatus::ReadyForPickup, $order->delivery_status);

        $order = $this->operations->perform($order, AdminOrderAction::ConfirmPickup, $this->admin);
        $this->assertSame(OrderStatus::Completed, $order->order_status);
        $this->assertSame(DeliveryStatus::PickedUp, $order->delivery_status);
    }

    public function test_unpaid_cancellation_releases_active_reservation_and_stock_atomically(): void
    {
        Queue::fake();
        [$order, $product, $reservation] = $this->orderWithReservation(
            paymentStatus: PaymentStatus::Pending,
            reservationStatus: ReservationStatus::Active,
        );

        $order = $this->operations->perform(
            $order,
            AdminOrderAction::Cancel,
            $this->admin,
            'El cliente solicito cancelar',
        );

        $this->assertSame(OrderStatus::Cancelled, $order->order_status);
        $this->assertSame(DeliveryStatus::Cancelled, $order->delivery_status);
        $this->assertSame(PaymentStatus::Pending, $order->payment_status);
        $this->assertSame(10, $product->refresh()->stock);
        $this->assertSame(ReservationStatus::Released, $reservation->refresh()->status);
        $this->assertNotNull($reservation->release_inventory_movement_id);
        $this->assertNull($reservation->restocked_at);
        $this->assertCancellationNotificationWasQueued($order);
    }

    public function test_paid_cancellation_restocks_consumed_reservation_once_and_marks_refund_pending(): void
    {
        Queue::fake();
        [$order, $product, $reservation] = $this->orderWithReservation(
            paymentStatus: PaymentStatus::Paid,
            reservationStatus: ReservationStatus::Consumed,
            orderStatus: OrderStatus::Processing,
            deliveryStatus: DeliveryStatus::Preparing,
        );
        $movementCount = InventoryMovement::query()->count();

        $order = $this->operations->perform(
            $order,
            AdminOrderAction::Cancel,
            $this->admin,
            'Producto danado antes del despacho',
        );

        $reservation->refresh();
        $this->assertSame(OrderStatus::Cancelled, $order->order_status);
        $this->assertSame(DeliveryStatus::Cancelled, $order->delivery_status);
        $this->assertSame(PaymentStatus::RefundPending, $order->payment_status);
        $this->assertSame(10, $product->refresh()->stock);
        $this->assertSame($movementCount + 1, InventoryMovement::query()->count());
        $this->assertSame(ReservationStatus::Consumed, $reservation->status);
        $this->assertNotNull($reservation->restocked_at);
        $this->assertNotNull($reservation->restock_inventory_movement_id);
        $this->assertSame('Producto danado antes del despacho', $reservation->restock_reason);
        $this->assertSame(
            InventoryMovement::TYPE_IN,
            $reservation->restockInventoryMovement->type,
        );

        $history = OrderStatusHistory::query()
            ->where('order_id', $order->id)
            ->where('reason', 'Producto danado antes del despacho')
            ->get();

        $this->assertCount(4, $history);
        $this->assertSame(
            [
                OrderHistoryDomain::Reservation,
                OrderHistoryDomain::Delivery,
                OrderHistoryDomain::Payment,
                OrderHistoryDomain::Order,
            ],
            $history->pluck('domain')->all(),
        );
        $this->assertTrue($history->every(fn (OrderStatusHistory $entry): bool => $entry->actor_id === $this->admin->id));
        $this->assertSame(1, $history->pluck('metadata.operation_reference')->unique()->count());

        $restockHistory = OrderStatusHistory::query()
            ->where('order_id', $order->id)
            ->where('domain', OrderHistoryDomain::Reservation->value)
            ->where('reason', 'Producto danado antes del despacho')
            ->firstOrFail();
        $this->assertSame('restocked', data_get($restockHistory->metadata, 'event'));
        $this->assertSame(
            data_get($history->first()->metadata, 'operation_reference'),
            data_get($restockHistory->metadata, 'operation_reference'),
        );
        $this->assertCancellationNotificationWasQueued($order);
    }

    public function test_repeating_a_completed_action_is_idempotent(): void
    {
        [$order, $product] = $this->orderWithReservation(
            paymentStatus: PaymentStatus::Paid,
            reservationStatus: ReservationStatus::Consumed,
        );

        $this->operations->perform($order, AdminOrderAction::Cancel, $this->admin, 'Cancelacion aprobada');
        $historyCount = OrderStatusHistory::query()->count();
        $movementCount = InventoryMovement::query()->count();

        $this->operations->perform($order, AdminOrderAction::Cancel, $this->admin, 'Cancelacion aprobada');

        $this->assertSame(10, $product->refresh()->stock);
        $this->assertSame($historyCount, OrderStatusHistory::query()->count());
        $this->assertSame($movementCount, InventoryMovement::query()->count());
    }

    public function test_invalid_actions_and_non_admin_actors_are_rejected_without_changes(): void
    {
        $pickup = Order::factory()->paid()->pickup()->create();
        $customer = User::factory()->create();

        try {
            $this->operations->perform($pickup, AdminOrderAction::MarkShipped, $this->admin);
            $this->fail('La accion incompatible debio ser rechazada.');
        } catch (DomainException $exception) {
            $this->assertStringContainsString('ya no esta disponible', $exception->getMessage());
        }

        $this->expectException(DomainException::class);
        $this->expectExceptionMessage('Solo un administrador');
        $this->operations->perform($pickup, AdminOrderAction::StartPreparation, $customer);
    }

    public function test_cancel_requires_a_meaningful_reason_at_domain_level(): void
    {
        $order = Order::factory()->create();

        $this->expectException(DomainException::class);
        $this->expectExceptionMessage('motivo');
        $this->operations->perform($order, AdminOrderAction::Cancel, $this->admin, '   ');
    }

    public function test_payment_cannot_jump_directly_from_paid_to_refunded(): void
    {
        $order = Order::factory()->paid()->create();

        $this->expectException(InvalidStateTransitionException::class);
        app(OrderStateTransitionService::class)->transitionPayment(
            $order,
            PaymentStatus::Refunded,
            $this->admin,
        );
    }

    public function test_refund_payment_flow_requires_the_pending_intermediate_state(): void
    {
        $order = Order::factory()->paid()->create();
        $states = app(OrderStateTransitionService::class);

        $order = $states->transitionPayment(
            $order,
            PaymentStatus::RefundPending,
            $this->admin,
            'Cancelacion pagada',
        );
        $this->assertSame(PaymentStatus::RefundPending, $order->payment_status);

        $order = $states->transitionPayment(
            $order,
            PaymentStatus::Refunded,
            reason: 'Reembolso confirmado por la pasarela',
        );
        $this->assertSame(PaymentStatus::Refunded, $order->payment_status);
    }

    public function test_compound_operation_rolls_back_first_transition_when_second_transition_fails(): void
    {
        $order = Order::factory()->paid()->create();
        $historyCount = OrderStatusHistory::query()->count();

        DB::statement(<<<'SQL'
            CREATE TRIGGER fail_preparing_delivery
            BEFORE UPDATE OF delivery_status ON orders
            WHEN NEW.delivery_status = 'preparing'
            BEGIN
                SELECT RAISE(ABORT, 'forced rollback');
            END
            SQL);

        try {
            $this->operations->perform($order, AdminOrderAction::StartPreparation, $this->admin);
            $this->fail('La transaccion debio fallar.');
        } catch (QueryException $exception) {
            $this->assertStringContainsString('forced rollback', $exception->getMessage());
        }

        $order->refresh();
        $this->assertSame(OrderStatus::PendingPayment, $order->order_status);
        $this->assertSame(DeliveryStatus::Pending, $order->delivery_status);
        $this->assertSame($historyCount, OrderStatusHistory::query()->count());
    }

    /**
     * @return array{Order, Product, StockReservation}
     */
    private function orderWithReservation(
        PaymentStatus $paymentStatus,
        ReservationStatus $reservationStatus,
        OrderStatus $orderStatus = OrderStatus::PendingPayment,
        DeliveryStatus $deliveryStatus = DeliveryStatus::Pending,
    ): array {
        $order = Order::factory()->create([
            'order_status' => $orderStatus,
            'payment_status' => $paymentStatus,
            'delivery_status' => $deliveryStatus,
            'paid_at' => $paymentStatus === PaymentStatus::Paid ? now() : null,
        ]);
        $product = Product::factory()->create(['stock' => 7]);
        $item = OrderItem::factory()
            ->for($order)
            ->for($product)
            ->create(['quantity' => 3]);
        $reservation = StockReservation::factory()->forOrderItem($item)->create([
            'quantity' => 3,
            'status' => $reservationStatus,
            'consumed_at' => $reservationStatus === ReservationStatus::Consumed ? now() : null,
        ]);

        return [$order, $product, $reservation];
    }

    private function assertCancellationNotificationWasQueued(Order $order): void
    {
        $deliveryIds = OrderNotificationDelivery::query()
            ->where('order_id', $order->getKey())
            ->where('type', OrderNotificationType::Cancelled->value)
            ->pluck('id');

        $this->assertNotEmpty($deliveryIds);
        Queue::assertPushed(
            SendOrderTransactionalEmail::class,
            fn (SendOrderTransactionalEmail $job): bool => $deliveryIds->contains($job->deliveryId),
        );
    }
}
