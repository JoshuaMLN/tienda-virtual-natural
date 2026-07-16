<?php

namespace Tests\Feature\Orders;

use App\Enums\DeliveryMethod;
use App\Enums\DeliveryStatus;
use App\Enums\OrderHistoryDomain;
use App\Enums\OrderStatus;
use App\Enums\PaymentStatus;
use App\Models\CustomerAddress;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Product;
use App\Models\StockReservation;
use App\Models\User;
use App\Support\Orders\InvalidStateTransitionException;
use App\Support\Orders\OrderStateTransitionService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use LogicException;
use Tests\TestCase;

class OrderDomainTest extends TestCase
{
    use RefreshDatabase;

    public function test_order_relations_are_optional_and_commercial_snapshots_survive_source_changes_and_deletions(): void
    {
        $user = User::factory()->create([
            'name' => 'Maria Fernanda Perez',
            'email' => 'maria@example.test',
            'phone' => '987654321',
        ]);
        $address = CustomerAddress::factory()->for($user)->create([
            'recipient_name' => 'Maria Fernanda Perez',
            'phone' => '987654321',
            'department' => 'Lima',
            'province' => 'Lima',
            'district' => 'San Isidro',
            'ubigeo' => '150131',
            'address_line' => 'Av. Camino Real 1234',
            'reference' => 'Frente al parque',
        ]);
        $product = Product::factory()->create([
            'name' => 'Omega 3 Premium',
            'sku' => 'SKU-OMEGA-120',
            'price' => 79.90,
        ]);
        $order = Order::factory()
            ->for($user)
            ->for($address, 'customerAddress')
            ->create([
                'customer_name' => 'Maria Fernanda Perez',
                'customer_email' => 'maria@example.test',
                'customer_phone' => '987654321',
                'delivery_recipient_name' => 'Maria Fernanda Perez',
                'delivery_phone' => '987654321',
                'delivery_department' => 'Lima',
                'delivery_province' => 'Lima',
                'delivery_district' => 'San Isidro',
                'delivery_ubigeo' => '150131',
                'delivery_address' => 'Av. Camino Real 1234',
                'delivery_reference' => 'Frente al parque',
            ]);
        $item = OrderItem::factory()->for($order)->for($product)->create([
            'product_sku' => 'SKU-OMEGA-120',
            'product_name' => 'Omega 3 Premium',
            'product_image' => 'products/omega-original.webp',
            'product_presentation' => '120 capsulas',
            'unit_price_cents' => 7990,
            'gross_total_cents' => 7990,
            'net_value_cents' => 6771,
            'tax_cents' => 1219,
            'total_cents' => 7990,
        ]);

        $this->assertTrue($order->user->is($user));
        $this->assertTrue($order->customerAddress->is($address));
        $this->assertTrue($order->items->contains($item));
        $this->assertTrue($item->product->is($product));
        $this->assertTrue($product->orderItems->contains($item));

        $user->update([
            'name' => 'Nombre modificado',
            'email' => 'nuevo@example.test',
        ]);
        $address->update([
            'district' => 'Miraflores',
            'address_line' => 'Otra direccion 999',
        ]);
        $product->update([
            'name' => 'Producto renombrado',
            'sku' => 'SKU-NUEVO',
            'price' => 99.90,
        ]);

        $order->refresh();
        $item->refresh();

        $this->assertSame('Maria Fernanda Perez', $order->customer_name);
        $this->assertSame('maria@example.test', $order->customer_email);
        $this->assertSame('San Isidro', $order->delivery_district);
        $this->assertSame('Av. Camino Real 1234', $order->delivery_address);
        $this->assertSame('SKU-OMEGA-120', $item->product_sku);
        $this->assertSame('Omega 3 Premium', $item->product_name);
        $this->assertSame('products/omega-original.webp', $item->product_image);
        $this->assertSame(7990, $item->unit_price_cents);

        $user->delete();
        $product->delete();

        $order->refresh();
        $item->refresh();

        $this->assertNull($order->user_id);
        $this->assertNull($order->customer_address_id);
        $this->assertNull($order->user);
        $this->assertNull($order->customerAddress);
        $this->assertNull($item->product_id);
        $this->assertNull($item->product);
        $this->assertSame('Maria Fernanda Perez', $order->customer_name);
        $this->assertSame('Av. Camino Real 1234', $order->delivery_address);
        $this->assertSame('SKU-OMEGA-120', $item->product_sku);
        $this->assertSame('Omega 3 Premium', $item->product_name);
        $this->assertSame(7990, $item->total_cents);
        $this->assertDatabaseHas('orders', ['id' => $order->id]);
        $this->assertDatabaseHas('order_items', ['id' => $item->id]);
    }

    public function test_valid_home_delivery_lifecycle_records_every_transition_and_is_idempotent(): void
    {
        $actor = User::factory()->admin()->create();
        $order = Order::factory()->create([
            'delivery_method' => DeliveryMethod::HomeDelivery,
        ]);
        $service = $this->states();

        $order = $service->transitionPayment(
            $order,
            PaymentStatus::Paid,
            $actor,
            'Pago confirmado',
            ['provider_reference' => 'PAY-100'],
        );
        $order = $service->transitionOrder($order, OrderStatus::Processing, $actor);
        $order = $service->transitionDelivery($order, DeliveryStatus::Preparing, $actor);
        $order = $service->transitionDelivery($order, DeliveryStatus::Shipped, $actor);
        $order = $service->transitionDelivery($order, DeliveryStatus::Delivered, $actor);
        $order = $service->transitionOrder($order, OrderStatus::Completed, $actor);

        $this->assertSame(PaymentStatus::Paid, $order->payment_status);
        $this->assertSame(OrderStatus::Completed, $order->order_status);
        $this->assertSame(DeliveryStatus::Delivered, $order->delivery_status);
        $this->assertNotNull($order->paid_at);
        $this->assertNotNull($order->delivery_window_starts_at);
        $this->assertNotNull($order->completed_at);

        $this->assertDatabaseHas('order_status_histories', [
            'order_id' => $order->id,
            'domain' => OrderHistoryDomain::Payment->value,
            'from_status' => PaymentStatus::Pending->value,
            'to_status' => PaymentStatus::Paid->value,
            'actor_id' => $actor->id,
            'reason' => 'Pago confirmado',
        ]);
        $this->assertDatabaseHas('order_status_histories', [
            'order_id' => $order->id,
            'domain' => OrderHistoryDomain::Order->value,
            'from_status' => OrderStatus::Processing->value,
            'to_status' => OrderStatus::Completed->value,
        ]);
        $this->assertDatabaseHas('order_status_histories', [
            'order_id' => $order->id,
            'domain' => OrderHistoryDomain::Delivery->value,
            'from_status' => DeliveryStatus::Shipped->value,
            'to_status' => DeliveryStatus::Delivered->value,
        ]);

        $historyCount = $order->statusHistories()->count();
        $sameOrder = $service->transitionOrder($order, OrderStatus::Completed, $actor);

        $this->assertSame($historyCount, $sameOrder->statusHistories()->count());
    }

    public function test_pickup_lifecycle_uses_pickup_states_and_can_complete(): void
    {
        $service = $this->states();
        $order = Order::factory()->pickup()->paid()->create();

        $order = $service->transitionOrder($order, OrderStatus::Processing);
        $order = $service->transitionDelivery($order, DeliveryStatus::Preparing);
        $order = $service->transitionDelivery($order, DeliveryStatus::ReadyForPickup);
        $order = $service->transitionDelivery($order, DeliveryStatus::PickedUp);
        $order = $service->transitionOrder($order, OrderStatus::Completed);

        $this->assertSame(OrderStatus::Completed, $order->order_status);
        $this->assertSame(DeliveryStatus::PickedUp, $order->delivery_status);
    }

    public function test_invalid_transitions_are_rejected_without_mutating_state_or_history(): void
    {
        $service = $this->states();
        $unpaid = Order::factory()->create();
        $initialHistoryCount = $unpaid->statusHistories()->count();

        $this->assertInvalidTransition(
            fn () => $service->transitionOrder($unpaid, OrderStatus::Processing),
            'pedido',
            OrderStatus::PendingPayment->value,
            OrderStatus::Processing->value,
        );

        $unpaid->refresh();
        $this->assertSame(OrderStatus::PendingPayment, $unpaid->order_status);
        $this->assertSame($initialHistoryCount, $unpaid->statusHistories()->count());

        $this->assertInvalidTransition(
            fn () => $service->transitionDelivery($unpaid, DeliveryStatus::Preparing),
            'entrega',
            DeliveryStatus::Pending->value,
            DeliveryStatus::Preparing->value,
        );

        $pickup = Order::factory()->pickup()->paid()->create();
        $pickup = $service->transitionDelivery($pickup, DeliveryStatus::Preparing);
        $pickupHistoryCount = $pickup->statusHistories()->count();

        $this->assertInvalidTransition(
            fn () => $service->transitionDelivery($pickup, DeliveryStatus::Shipped),
            'entrega',
            DeliveryStatus::Preparing->value,
            DeliveryStatus::Shipped->value,
        );

        $pickup->refresh();
        $this->assertSame(DeliveryStatus::Preparing, $pickup->delivery_status);
        $this->assertSame($pickupHistoryCount, $pickup->statusHistories()->count());

        $processing = Order::factory()->processing()->create();
        $processingHistoryCount = $processing->statusHistories()->count();

        $this->assertInvalidTransition(
            fn () => $service->transitionOrder($processing, OrderStatus::Completed),
            'pedido',
            OrderStatus::Processing->value,
            OrderStatus::Completed->value,
        );

        $this->assertSame(OrderStatus::Processing, $processing->refresh()->order_status);
        $this->assertSame($processingHistoryCount, $processing->statusHistories()->count());
    }

    public function test_expiration_cancellation_and_processing_require_their_domain_preconditions(): void
    {
        $service = $this->states();
        $expiring = Order::factory()->create();

        $this->assertInvalidTransition(
            fn () => $service->transitionOrder($expiring, OrderStatus::Expired),
            'pedido',
            OrderStatus::PendingPayment->value,
            OrderStatus::Expired->value,
        );

        $expiring = $service->transitionPayment($expiring, PaymentStatus::Expired);
        $expiring = $service->transitionOrder($expiring, OrderStatus::Expired);

        $this->assertSame(PaymentStatus::Expired, $expiring->payment_status);
        $this->assertSame(OrderStatus::Expired, $expiring->order_status);

        $reserved = Order::factory()->paid()->create();
        $product = Product::factory()->create();
        $item = OrderItem::factory()->for($reserved)->for($product)->create();
        StockReservation::factory()->forOrderItem($item)->create();

        $this->assertInvalidTransition(
            fn () => $service->transitionOrder($reserved, OrderStatus::Processing),
            'pedido',
            OrderStatus::PendingPayment->value,
            OrderStatus::Processing->value,
        );
        $this->assertSame(OrderStatus::PendingPayment, $reserved->refresh()->order_status);

        $preparing = Order::factory()->paid()->create();
        $preparing = $service->transitionDelivery($preparing, DeliveryStatus::Preparing);

        $this->assertInvalidTransition(
            fn () => $service->transitionOrder($preparing, OrderStatus::Cancelled),
            'pedido',
            OrderStatus::PendingPayment->value,
            OrderStatus::Cancelled->value,
        );

        $preparing = $service->transitionDelivery($preparing, DeliveryStatus::Cancelled);
        $preparing = $service->transitionOrder($preparing, OrderStatus::Cancelled);

        $this->assertSame(DeliveryStatus::Cancelled, $preparing->delivery_status);
        $this->assertSame(OrderStatus::Cancelled, $preparing->order_status);
    }

    public function test_orders_and_items_cannot_be_modified_or_deleted_outside_domain_services(): void
    {
        $order = Order::factory()->create();
        $item = OrderItem::factory()->for($order)->create();
        $originalItemName = $item->product_name;

        $this->assertLogicException(function () use ($order): void {
            $order->order_status = OrderStatus::Cancelled;
            $order->save();
        });

        $this->assertSame(OrderStatus::PendingPayment, $order->fresh()->order_status);
        $this->assertLogicException(fn () => $order->fresh()->update(['customer_name' => 'Alterado']));
        $this->assertLogicException(fn () => $order->fresh()->delete());
        $this->assertLogicException(fn () => $item->update(['product_name' => 'Alterado']));
        $this->assertLogicException(fn () => $item->fresh()->delete());

        $this->assertDatabaseHas('orders', [
            'id' => $order->id,
            'customer_name' => $order->customer_name,
            'order_status' => OrderStatus::PendingPayment->value,
        ]);
        $this->assertDatabaseHas('order_items', [
            'id' => $item->id,
            'product_name' => $originalItemName,
        ]);
    }

    public function test_history_is_immutable_and_keeps_actor_and_metadata_snapshots_when_actor_changes_or_is_deleted(): void
    {
        $actor = User::factory()->admin()->create([
            'name' => 'Administrador Original',
            'email' => 'admin.original@example.test',
        ]);
        $order = Order::factory()->create();

        $this->states()->transitionPayment(
            $order,
            PaymentStatus::Failed,
            $actor,
            'Pago rechazado por el banco',
            ['provider_code' => 'DECLINED', 'attempt' => 1],
        );

        $history = $order->statusHistories()
            ->where('domain', OrderHistoryDomain::Payment->value)
            ->where('from_status', PaymentStatus::Pending->value)
            ->latest('id')
            ->firstOrFail();

        $this->assertTrue($history->actor->is($actor));
        $this->assertSame('Administrador Original', $history->actor_name);
        $this->assertSame('admin.original@example.test', $history->actor_email);
        $this->assertSame('DECLINED', $history->metadata['provider_code']);
        $this->assertSame(1, $history->metadata['attempt']);

        $actor->update([
            'name' => 'Administrador Renombrado',
            'email' => 'admin.nuevo@example.test',
        ]);
        $actor->delete();

        $history->refresh();

        $this->assertNull($history->actor_id);
        $this->assertNull($history->actor);
        $this->assertSame('Administrador Original', $history->actor_name);
        $this->assertSame('admin.original@example.test', $history->actor_email);
        $this->assertSame('Pago rechazado por el banco', $history->reason);
        $this->assertSame('DECLINED', $history->metadata['provider_code']);

        $this->assertLogicException(fn () => $history->update(['reason' => 'Alterado']));
        $this->assertLogicException(fn () => $history->fresh()->delete());

        $this->assertDatabaseHas('order_status_histories', [
            'id' => $history->id,
            'actor_id' => null,
            'actor_name' => 'Administrador Original',
            'actor_email' => 'admin.original@example.test',
            'reason' => 'Pago rechazado por el banco',
        ]);
    }

    private function states(): OrderStateTransitionService
    {
        return app(OrderStateTransitionService::class);
    }

    /** @param callable(): mixed $callback */
    private function assertInvalidTransition(callable $callback, string $domain, string $from, string $to): void
    {
        try {
            $callback();
            $this->fail('Se esperaba una transicion de estado invalida.');
        } catch (InvalidStateTransitionException $exception) {
            $this->assertSame($domain, $exception->domain);
            $this->assertSame($from, $exception->from);
            $this->assertSame($to, $exception->to);
        }
    }

    /** @param callable(): mixed $callback */
    private function assertLogicException(callable $callback): void
    {
        try {
            $callback();
            $this->fail('Se esperaba que la operacion estuviera protegida por el dominio.');
        } catch (LogicException $exception) {
            $this->assertNotSame('', $exception->getMessage());
        }
    }
}
