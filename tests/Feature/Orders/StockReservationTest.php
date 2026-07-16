<?php

namespace Tests\Feature\Orders;

use App\Enums\DeliveryStatus;
use App\Enums\OrderHistoryDomain;
use App\Enums\OrderStatus;
use App\Enums\PaymentStatus;
use App\Enums\ReservationStatus;
use App\Models\InventoryMovement;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Product;
use App\Models\StockReservation;
use App\Models\User;
use App\Support\Inventory\InsufficientStockException;
use App\Support\Orders\InvalidStateTransitionException;
use App\Support\Orders\OrderPaymentService;
use App\Support\Orders\OrderStateTransitionService;
use App\Support\Orders\Reservations\InvalidReservationTransitionException;
use App\Support\Orders\Reservations\StockReservationService;
use Carbon\CarbonImmutable;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use LogicException;
use Tests\TestCase;

class StockReservationTest extends TestCase
{
    use RefreshDatabase;

    public function test_reserving_stock_decrements_inventory_once_and_records_auditable_relations(): void
    {
        [$order, $item, $product] = $this->reservableOrder(stock: 10, quantity: 3);
        $actor = User::factory()->admin()->create();
        $expiresAt = CarbonImmutable::now()->startOfSecond()->addMinutes(20);

        $reservation = $this->reservations()->reserve($item, $expiresAt, $actor);

        $this->assertSame(7, $product->refresh()->stock);
        $this->assertSame(ReservationStatus::Active, $reservation->status);
        $this->assertSame(3, $reservation->quantity);
        $this->assertSame($expiresAt->format('Y-m-d H:i:s'), $reservation->expires_at->format('Y-m-d H:i:s'));
        $this->assertTrue($reservation->orderItem->is($item), 'La reserva debe pertenecer al item.');
        $this->assertTrue($item->fresh()->stockReservation->is($reservation), 'El item debe exponer su reserva.');
        $this->assertTrue($order->stockReservations->contains($reservation), 'El pedido debe exponer sus reservas mediante sus items.');

        $movement = $reservation->reserveInventoryMovement;

        $this->assertNotNull($movement);
        $this->assertSame(InventoryMovement::TYPE_OUT, $movement->type);
        $this->assertSame(3, $movement->quantity);
        $this->assertSame(10, $movement->stock_before);
        $this->assertSame(7, $movement->stock_after);
        $this->assertSame('Reserva de stock', $movement->reason);
        $this->assertSame($order->code, $movement->reference);
        $this->assertTrue($movement->startedReservations->contains($reservation), 'El movimiento debe enlazar la reserva iniciada.');

        $history = $order->statusHistories()
            ->where('domain', OrderHistoryDomain::Reservation->value)
            ->latest('id')
            ->firstOrFail();

        $this->assertNull($history->from_status);
        $this->assertSame(ReservationStatus::Active->value, $history->to_status);
        $this->assertSame($actor->id, $history->actor_id);
        $this->assertSame($reservation->id, $history->metadata['reservation_id']);
        $this->assertSame($item->id, $history->metadata['order_item_id']);
        $this->assertSame($product->id, $history->metadata['product_id']);
        $this->assertSame(3, $history->metadata['quantity']);
        $this->assertSame(
            $expiresAt->format('Y-m-d H:i:s'),
            $order->refresh()->reservation_expires_at->format('Y-m-d H:i:s'),
        );
    }

    public function test_consuming_a_reservation_is_idempotent_and_never_decrements_or_restores_stock_twice(): void
    {
        [$order, $item, $product] = $this->reservableOrder(stock: 8, quantity: 2);
        $reservation = $this->reservations()->reserve($item, now()->addMinutes(15));

        $consumed = $this->reservations()->consume($reservation);
        $historyCount = $order->statusHistories()->where('domain', OrderHistoryDomain::Reservation->value)->count();
        $movementCount = InventoryMovement::query()->count();
        $consumedAgain = $this->reservations()->consume($consumed);

        $this->assertSame(ReservationStatus::Consumed, $consumedAgain->status);
        $this->assertNotNull($consumedAgain->consumed_at);
        $this->assertNull($consumedAgain->released_at);
        $this->assertNull($consumedAgain->release_inventory_movement_id);
        $this->assertSame(6, $product->refresh()->stock);
        $this->assertSame($movementCount, InventoryMovement::query()->count());
        $this->assertSame(
            $historyCount,
            $order->statusHistories()->where('domain', OrderHistoryDomain::Reservation->value)->count(),
        );
        $this->assertDatabaseHas('order_status_histories', [
            'order_id' => $order->id,
            'domain' => OrderHistoryDomain::Reservation->value,
            'from_status' => ReservationStatus::Active->value,
            'to_status' => ReservationStatus::Consumed->value,
        ]);
    }

    public function test_confirming_payment_consumes_reservations_and_stock_remains_sold(): void
    {
        [$order, $item, $product] = $this->reservableOrder(stock: 8, quantity: 2);
        $reservation = $this->reservations()->reserve($item, now()->addMinutes(15));

        try {
            app(OrderStateTransitionService::class)->transitionPayment($order, PaymentStatus::Paid);
            $this->fail('Se esperaba exigir el coordinador transaccional de pago.');
        } catch (InvalidStateTransitionException $exception) {
            $this->assertStringContainsString('servicio de pago', $exception->getMessage());
        }

        $paid = app(OrderPaymentService::class)->markPaid(
            $order,
            reason: 'Pago confirmado por el proveedor',
            metadata: ['provider_reference' => 'PAY-123'],
        );

        $this->assertSame(PaymentStatus::Paid, $paid->payment_status);
        $this->assertSame(ReservationStatus::Consumed, $reservation->refresh()->status);
        $this->assertSame(6, $product->refresh()->stock);
        $this->assertDatabaseCount('inventory_movements', 1);
        $this->assertDatabaseHas('order_status_histories', [
            'order_id' => $order->id,
            'domain' => OrderHistoryDomain::Payment->value,
            'to_status' => PaymentStatus::Paid->value,
            'reason' => 'Pago confirmado por el proveedor',
        ]);

        try {
            $this->reservations()->expireForOrder($paid, now()->addHour());
            $this->fail('Se esperaba impedir el vencimiento de un pedido pagado.');
        } catch (\DomainException $exception) {
            $this->assertStringContainsString('pendiente de pago', $exception->getMessage());
        }

        $this->assertSame(6, $product->refresh()->stock);
        $this->assertSame(ReservationStatus::Consumed, $reservation->refresh()->status);
    }

    public function test_releasing_a_reservation_restores_stock_exactly_once_and_is_idempotent(): void
    {
        [$order, $item, $product] = $this->reservableOrder(stock: 12, quantity: 5);
        $reservation = $this->reservations()->reserve($item, now()->addMinutes(15));

        $released = $this->reservations()->release($reservation, 'Pago cancelado por el cliente');

        $this->assertSame(ReservationStatus::Released, $released->status);
        $this->assertNotNull($released->released_at);
        $this->assertSame('Pago cancelado por el cliente', $released->release_reason);
        $this->assertSame(12, $product->refresh()->stock);
        $this->assertNotNull($released->releaseInventoryMovement);
        $this->assertSame(InventoryMovement::TYPE_IN, $released->releaseInventoryMovement->type);
        $this->assertSame(5, $released->releaseInventoryMovement->quantity);
        $this->assertSame(7, $released->releaseInventoryMovement->stock_before);
        $this->assertSame(12, $released->releaseInventoryMovement->stock_after);
        $this->assertTrue($released->releaseInventoryMovement->releasedReservations->contains($released));

        $movementCount = InventoryMovement::query()->count();
        $historyCount = $order->statusHistories()->where('domain', OrderHistoryDomain::Reservation->value)->count();
        $releasedAgain = $this->reservations()->release($released, 'Segundo intento');

        $this->assertSame(ReservationStatus::Released, $releasedAgain->status);
        $this->assertSame('Pago cancelado por el cliente', $releasedAgain->release_reason);
        $this->assertSame(12, $product->refresh()->stock);
        $this->assertSame($movementCount, InventoryMovement::query()->count());
        $this->assertSame(
            $historyCount,
            $order->statusHistories()->where('domain', OrderHistoryDomain::Reservation->value)->count(),
        );
    }

    public function test_cancelling_an_order_releases_active_reservations_and_transitions_order_and_delivery(): void
    {
        [$order, $item, $product] = $this->reservableOrder(stock: 9, quantity: 4);
        $reservation = $this->reservations()->reserve($item, now()->addMinutes(15));

        $cancelled = $this->reservations()->releaseForCancellation(
            $order,
            reason: 'Cliente solicito la cancelacion',
        );

        $this->assertSame(9, $product->refresh()->stock);
        $this->assertSame(ReservationStatus::Released, $reservation->refresh()->status);
        $this->assertSame('Cliente solicito la cancelacion', $reservation->release_reason);
        $this->assertSame(OrderStatus::Cancelled, $cancelled->order_status);
        $this->assertSame(DeliveryStatus::Cancelled, $cancelled->delivery_status);
        $this->assertSame(PaymentStatus::Pending, $cancelled->payment_status);
        $this->assertNotNull($cancelled->cancelled_at);
        $this->assertDatabaseHas('order_status_histories', [
            'order_id' => $order->id,
            'domain' => OrderHistoryDomain::Order->value,
            'from_status' => OrderStatus::PendingPayment->value,
            'to_status' => OrderStatus::Cancelled->value,
            'reason' => 'Cliente solicito la cancelacion',
        ]);
        $this->assertDatabaseHas('order_status_histories', [
            'order_id' => $order->id,
            'domain' => OrderHistoryDomain::Delivery->value,
            'from_status' => DeliveryStatus::Pending->value,
            'to_status' => DeliveryStatus::Cancelled->value,
        ]);
    }

    public function test_expiring_an_order_restores_stock_expires_active_reservations_and_is_idempotent(): void
    {
        [$order, $item, $product] = $this->reservableOrder(stock: 6, quantity: 2);
        $expiresAt = CarbonImmutable::now()->addMinutes(10);
        $reservation = $this->reservations()->reserve($item, $expiresAt);

        $expiredOrder = $this->reservations()->expireForOrder($order, $expiresAt);

        $this->assertSame(6, $product->refresh()->stock);
        $this->assertSame(ReservationStatus::Expired, $reservation->refresh()->status);
        $this->assertNotNull($reservation->expired_at);
        $this->assertNull($reservation->released_at);
        $this->assertSame('Vencimiento de la reserva', $reservation->release_reason);
        $this->assertSame(OrderStatus::Expired, $expiredOrder->order_status);
        $this->assertSame(PaymentStatus::Expired, $expiredOrder->payment_status);
        $this->assertNotNull($expiredOrder->expired_at);

        $movementCount = InventoryMovement::query()->count();
        $historyCount = $order->statusHistories()->count();
        $expiredAgain = $this->reservations()->expireForOrder($expiredOrder, $expiresAt->addMinute());

        $this->assertSame(OrderStatus::Expired, $expiredAgain->order_status);
        $this->assertSame(PaymentStatus::Expired, $expiredAgain->payment_status);
        $this->assertSame(6, $product->refresh()->stock);
        $this->assertSame($movementCount, InventoryMovement::query()->count());
        $this->assertSame($historyCount, $order->statusHistories()->count());
    }

    public function test_invalid_reservation_transitions_and_direct_status_changes_are_rejected(): void
    {
        [, $item] = $this->reservableOrder(stock: 10, quantity: 2);
        $expiresAt = CarbonImmutable::now()->addMinutes(15);
        $reservation = $this->reservations()->reserve($item, $expiresAt);

        try {
            $this->reservations()->expire($reservation, $expiresAt->subSecond());
            $this->fail('Se esperaba impedir el vencimiento anticipado.');
        } catch (InvalidReservationTransitionException $exception) {
            $this->assertStringContainsString('todavia no ha alcanzado', $exception->getMessage());
        }

        $this->assertSame(ReservationStatus::Active, $reservation->refresh()->status);

        try {
            $reservation->status = ReservationStatus::Released;
            $reservation->save();
            $this->fail('Se esperaba impedir el cambio directo de estado.');
        } catch (LogicException $exception) {
            $this->assertStringContainsString('servicio de reservas', $exception->getMessage());
        }

        $this->assertSame(ReservationStatus::Active, $reservation->fresh()->status);

        $consumed = $this->reservations()->consume($reservation->fresh());

        try {
            $this->reservations()->release($consumed, 'Intento invalido');
            $this->fail('Se esperaba impedir la liberacion de una reserva consumida.');
        } catch (InvalidReservationTransitionException $exception) {
            $this->assertStringContainsString('consumed', $exception->getMessage());
        }

        $this->assertSame(ReservationStatus::Consumed, $consumed->fresh()->status);
    }

    public function test_expired_reservation_cannot_be_consumed(): void
    {
        [, $item, $product] = $this->reservableOrder(stock: 10, quantity: 2);
        $expiresAt = CarbonImmutable::now()->startOfSecond()->addMinutes(5);
        $reservation = $this->reservations()->reserve($item, $expiresAt);

        CarbonImmutable::setTestNow($expiresAt);

        try {
            $this->reservations()->consume($reservation);
            $this->fail('Se esperaba impedir el consumo de una reserva vencida.');
        } catch (InvalidReservationTransitionException $exception) {
            $this->assertStringContainsString('vencida ya no se puede consumir', $exception->getMessage());
        } finally {
            CarbonImmutable::setTestNow();
        }

        $this->assertSame(ReservationStatus::Active, $reservation->refresh()->status);
        $this->assertSame(8, $product->refresh()->stock);
        $this->assertDatabaseCount('inventory_movements', 1);
    }

    public function test_reservations_and_inventory_movements_are_immutable_historical_records(): void
    {
        [, $item] = $this->reservableOrder(stock: 10, quantity: 2);
        $reservation = $this->reservations()->reserve($item, now()->addMinutes(15));
        $movement = $reservation->reserveInventoryMovement;

        $this->assertLogicException(fn () => $reservation->update(['quantity' => 1]));
        $this->assertLogicException(fn () => $reservation->fresh()->delete());
        $this->assertLogicException(fn () => $movement->update(['reason' => 'Alterado']));
        $this->assertLogicException(fn () => $movement->fresh()->delete());

        $this->assertDatabaseHas('stock_reservations', [
            'id' => $reservation->id,
            'quantity' => 2,
            'status' => ReservationStatus::Active->value,
        ]);
        $this->assertDatabaseHas('inventory_movements', [
            'id' => $movement->id,
            'reason' => 'Reserva de stock',
        ]);
    }

    public function test_inventory_history_survives_product_deletion_and_reservation_can_still_close(): void
    {
        [$order, $item, $product] = $this->reservableOrder(stock: 10, quantity: 2);
        $reservation = $this->reservations()->reserve($item, now()->addMinutes(15));
        $reserveMovement = $reservation->reserveInventoryMovement;

        $product->delete();

        $this->assertNull($reserveMovement->refresh()->product_id);
        $this->assertNull($item->refresh()->product_id);
        $this->assertDatabaseHas('inventory_movements', [
            'id' => $reserveMovement->id,
            'stock_before' => 10,
            'stock_after' => 8,
            'reference' => $order->code,
        ]);

        $released = $this->reservations()->release($reservation->refresh(), 'Producto eliminado del catalogo');

        $this->assertSame(ReservationStatus::Released, $released->status);
        $this->assertNull($released->release_inventory_movement_id);
        $this->assertDatabaseCount('inventory_movements', 1);
        $this->assertDatabaseHas('order_status_histories', [
            'order_id' => $order->id,
            'domain' => OrderHistoryDomain::Reservation->value,
            'to_status' => ReservationStatus::Released->value,
        ]);
    }

    public function test_only_one_reservation_can_exist_for_each_order_item(): void
    {
        [, $item] = $this->reservableOrder();
        StockReservation::factory()->forOrderItem($item)->create();

        $this->expectException(QueryException::class);

        StockReservation::factory()->forOrderItem($item)->create();
    }

    public function test_failed_reservation_due_to_insufficient_stock_rolls_back_every_side_effect(): void
    {
        [$order, $item, $product] = $this->reservableOrder(stock: 2, quantity: 3);
        $initialHistoryCount = $order->statusHistories()->count();

        try {
            $this->reservations()->reserve($item, now()->addMinutes(15));
            $this->fail('Se esperaba stock insuficiente.');
        } catch (InsufficientStockException $exception) {
            $this->assertSame(3, $exception->requestedQuantity);
            $this->assertSame(2, $exception->availableStock);
        }

        $this->assertSame(2, $product->refresh()->stock);
        $this->assertDatabaseCount('stock_reservations', 0);
        $this->assertDatabaseCount('inventory_movements', 0);
        $this->assertSame($initialHistoryCount, $order->statusHistories()->count());
    }

    /** @return array{Order, OrderItem, Product} */
    private function reservableOrder(int $stock = 10, int $quantity = 2): array
    {
        $product = Product::factory()->create(['stock' => $stock]);
        $order = Order::factory()->create(['reservation_expires_at' => null]);
        $item = OrderItem::factory()->for($order)->for($product)->create([
            'product_sku' => $product->sku,
            'product_name' => $product->name,
            'quantity' => $quantity,
        ]);

        return [$order, $item, $product];
    }

    private function reservations(): StockReservationService
    {
        return app(StockReservationService::class);
    }

    /** @param callable(): mixed $callback */
    private function assertLogicException(callable $callback): void
    {
        try {
            $callback();
            $this->fail('Se esperaba que el registro historico fuera inmutable.');
        } catch (LogicException $exception) {
            $this->assertNotSame('', $exception->getMessage());
        }
    }
}
