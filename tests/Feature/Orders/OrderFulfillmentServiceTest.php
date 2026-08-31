<?php

namespace Tests\Feature\Orders;

use App\Enums\AdminOrderAction;
use App\Enums\DeliveryAttemptAttribution;
use App\Enums\DeliveryAttemptResult;
use App\Enums\DeliveryStatus;
use App\Enums\DeliveryTrackingStatus;
use App\Enums\OrderStatus;
use App\Models\DeliveryAttempt;
use App\Models\Order;
use App\Models\User;
use App\Support\Orders\AdminOrderOperationService;
use App\Support\Orders\OrderFulfillmentService;
use Carbon\CarbonImmutable;
use DomainException;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use LogicException;
use Tests\TestCase;

class OrderFulfillmentServiceTest extends TestCase
{
    use RefreshDatabase;

    private OrderFulfillmentService $fulfillment;

    private AdminOrderOperationService $operations;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();

        CarbonImmutable::setTestNow('2026-07-31 10:00:00');
        $this->fulfillment = app(OrderFulfillmentService::class);
        $this->operations = app(AdminOrderOperationService::class);
        $this->admin = User::factory()->admin()->create([
            'name' => 'Maria Operaciones',
            'email' => 'operaciones@example.test',
        ]);
    }

    protected function tearDown(): void
    {
        CarbonImmutable::setTestNow();

        parent::tearDown();
    }

    public function test_delivered_result_completes_delivery_order_and_tracking_atomically(): void
    {
        $order = Order::factory()->shipped()->create();

        $attempt = $this->record(
            $order,
            DeliveryAttemptResult::Delivered,
            DeliveryAttemptAttribution::Unattributed,
            null,
        );

        $order->refresh();
        $this->assertSame(DeliveryStatus::Delivered, $order->delivery_status);
        $this->assertSame(OrderStatus::Completed, $order->order_status);
        $this->assertSame(DeliveryTrackingStatus::Completed, $order->delivery_tracking_status);
        $this->assertNotNull($order->completed_at);
        $this->assertNotNull($order->delivery_tracking_completed_at);
        $this->assertFalse($attempt->consumes_attempt);
        $this->assertNull($attempt->counted_attempt_number);
        $this->assertDatabaseHas('order_status_histories', [
            'order_id' => $order->id,
            'domain' => 'delivery',
            'from_status' => DeliveryStatus::Shipped->value,
            'to_status' => DeliveryStatus::Delivered->value,
        ]);
        $this->assertDatabaseHas('order_status_histories', [
            'order_id' => $order->id,
            'domain' => 'order',
            'from_status' => OrderStatus::Processing->value,
            'to_status' => OrderStatus::Completed->value,
        ]);
    }

    public function test_only_customer_incidents_consume_attempts_and_sequences_remain_auditable(): void
    {
        $order = Order::factory()->shipped()->create(['delivery_attempts_per_cycle' => 3]);

        $carrier = $this->record(
            $order,
            DeliveryAttemptResult::Incident,
            DeliveryAttemptAttribution::Carrier,
            'Vehiculo averiado durante la ruta.',
        );
        $store = $this->record(
            $order,
            DeliveryAttemptResult::Incident,
            DeliveryAttemptAttribution::Store,
            'La direccion fue registrada de forma incompleta.',
        );
        $unattributed = $this->record(
            $order,
            DeliveryAttemptResult::Incident,
            DeliveryAttemptAttribution::Unattributed,
            'Una causa externa impidio completar la visita.',
        );
        $customer = $this->record(
            $order,
            DeliveryAttemptResult::Incident,
            DeliveryAttemptAttribution::Customer,
            'No habia una persona disponible para recibir.',
        );

        $this->assertSame(
            [1, 2, 3, 4],
            [$carrier->attempt_number, $store->attempt_number, $unattributed->attempt_number, $customer->attempt_number],
        );
        $this->assertNull($carrier->counted_attempt_number);
        $this->assertNull($store->counted_attempt_number);
        $this->assertNull($unattributed->counted_attempt_number);
        $this->assertSame(1, $customer->counted_attempt_number);
        $this->assertFalse($carrier->consumes_attempt);
        $this->assertFalse($store->consumes_attempt);
        $this->assertFalse($unattributed->consumes_attempt);
        $this->assertTrue($customer->consumes_attempt);
        $this->assertSame(DeliveryTrackingStatus::Active, $order->refresh()->delivery_tracking_status);
    }

    public function test_exhausting_a_cycle_blocks_visits_until_a_new_shipping_payment_is_confirmed(): void
    {
        $order = Order::factory()->shipped()->create([
            'delivery_attempts_per_cycle' => 2,
            'delivery_max_automatic_cycles' => 2,
            'reshipment_payment_days' => 7,
        ]);

        $this->customerIncident($order, 'Primera visita sin respuesta del cliente.');
        $second = $this->customerIncident($order, 'Segunda visita sin respuesta del cliente.');

        $order->refresh();
        $this->assertSame(2, $second->counted_attempt_number);
        $this->assertSame(DeliveryTrackingStatus::AwaitingReshipmentPayment, $order->delivery_tracking_status);
        $this->assertSame('2026-08-07 10:00:00', $order->reshipment_payment_due_at->format('Y-m-d H:i:s'));
        $this->assertFalse($this->fulfillment->canRecordDeliveryAttempt($order));

        $this->expectException(DomainException::class);
        $this->expectExceptionMessage('nuevo pago de envio');
        $this->customerIncident($order, 'Una visita que ya no debe registrarse.');
    }

    public function test_exhausting_the_last_automatic_cycle_moves_the_order_to_manual_follow_up(): void
    {
        $order = Order::factory()->shipped()->create([
            'delivery_current_cycle' => 2,
            'delivery_attempts_per_cycle' => 1,
            'delivery_max_automatic_cycles' => 2,
        ]);

        $this->customerIncident($order, 'El cliente no estuvo disponible en la ultima visita.');

        $order->refresh();
        $this->assertSame(DeliveryTrackingStatus::ManualFollowUp, $order->delivery_tracking_status);
        $this->assertNotNull($order->delivery_manual_follow_up_at);
        $this->assertNull($order->reshipment_payment_due_at);
        $this->assertSame(OrderStatus::Processing, $order->order_status);
        $this->assertSame(DeliveryStatus::Shipped, $order->delivery_status);
    }

    public function test_repeating_the_same_operation_token_is_idempotent(): void
    {
        $order = Order::factory()->shipped()->create();
        $token = (string) Str::uuid();

        $first = $this->record(
            $order,
            DeliveryAttemptResult::Incident,
            DeliveryAttemptAttribution::Customer,
            'No se encontro al cliente en el domicilio.',
            $token,
        );
        $second = $this->record(
            $order,
            DeliveryAttemptResult::Incident,
            DeliveryAttemptAttribution::Customer,
            'Este texto no debe reemplazar el snapshot original.',
            $token,
        );

        $this->assertTrue($first->is($second));
        $this->assertDatabaseCount('delivery_attempts', 1);
        $this->assertSame('No se encontro al cliente en el domicilio.', $second->reason);
    }

    public function test_invalid_actor_state_attribution_and_dates_are_rejected_without_partial_history(): void
    {
        $order = Order::factory()->shipped()->create();
        $customer = User::factory()->create();

        foreach ([
            fn () => $this->fulfillment->recordDeliveryAttempt(
                $order,
                $customer,
                (string) Str::uuid(),
                DeliveryAttemptResult::Incident,
                DeliveryAttemptAttribution::Customer,
                'Transportista',
                'Cliente ausente durante la visita.',
                now(),
            ),
            fn () => $this->record(
                $order,
                DeliveryAttemptResult::Delivered,
                DeliveryAttemptAttribution::Customer,
                null,
            ),
            fn () => $this->record(
                $order,
                DeliveryAttemptResult::Incident,
                DeliveryAttemptAttribution::Customer,
                'Fecha futura no permitida.',
                occurredAt: now()->addMinute(),
            ),
            fn () => $this->record(
                $order,
                DeliveryAttemptResult::Incident,
                DeliveryAttemptAttribution::Customer,
                'Fecha anterior al despacho.',
                occurredAt: now()->subMinute(),
            ),
            fn () => $this->fulfillment->recordDeliveryAttempt(
                $order,
                $this->admin,
                (string) Str::uuid(),
                DeliveryAttemptResult::Incident,
                DeliveryAttemptAttribution::Customer,
                str_repeat('R', 121),
                'Responsable demasiado extenso.',
                now(),
            ),
            fn () => $this->fulfillment->recordDeliveryAttempt(
                $order,
                $this->admin,
                (string) Str::uuid(),
                DeliveryAttemptResult::Incident,
                DeliveryAttemptAttribution::Customer,
                'Transportista',
                str_repeat('M', 501),
                now(),
            ),
        ] as $operation) {
            try {
                $operation();
                $this->fail('La operacion invalida debio ser rechazada.');
            } catch (DomainException) {
                $this->assertDatabaseCount('delivery_attempts', 0);
            }
        }
    }

    public function test_pickup_deadline_starts_when_ready_and_pickup_completion_closes_tracking(): void
    {
        $order = Order::factory()->paid()->pickup()->create(['pickup_hold_days' => 10]);

        $order = $this->operations->perform($order, AdminOrderAction::StartPreparation, $this->admin);
        $order = $this->operations->perform($order, AdminOrderAction::MarkReadyForPickup, $this->admin);

        $this->assertSame(DeliveryStatus::ReadyForPickup, $order->delivery_status);
        $this->assertSame('2026-07-31 10:00:00', $order->pickup_ready_at->format('Y-m-d H:i:s'));
        $this->assertSame('2026-08-10 10:00:00', $order->pickup_deadline_at->format('Y-m-d H:i:s'));

        $order = $this->operations->perform($order, AdminOrderAction::ConfirmPickup, $this->admin);
        $this->assertSame(OrderStatus::Completed, $order->order_status);
        $this->assertSame(DeliveryStatus::PickedUp, $order->delivery_status);
        $this->assertSame(DeliveryTrackingStatus::Completed, $order->delivery_tracking_status);
    }

    public function test_reconciliation_moves_due_cases_to_manual_follow_up_without_cancelling_orders(): void
    {
        $pickup = Order::factory()->readyForPickup()->create([
            'pickup_deadline_at' => now(),
        ]);
        $reshipment = Order::factory()->awaitingReshipmentPayment()->create([
            'reshipment_payment_due_at' => now(),
        ]);

        $this->assertSame(2, $this->fulfillment->reconcileFollowUps());

        foreach ([$pickup->refresh(), $reshipment->refresh()] as $order) {
            $this->assertSame(DeliveryTrackingStatus::ManualFollowUp, $order->delivery_tracking_status);
            $this->assertSame(OrderStatus::Processing, $order->order_status);
            $this->assertNotNull($order->delivery_manual_follow_up_at);
        }

        $this->assertSame(DeliveryStatus::ReadyForPickup, $pickup->delivery_status);
        $this->assertSame(DeliveryStatus::Shipped, $reshipment->delivery_status);
        $this->assertSame(0, $this->fulfillment->reconcileFollowUps());

        $pickupFollowUpAt = $pickup->delivery_manual_follow_up_at;
        $pickupDeadline = $pickup->pickup_deadline_at;
        $pickup = $this->operations->perform($pickup, AdminOrderAction::ConfirmPickup, $this->admin);
        $this->assertSame(DeliveryTrackingStatus::Completed, $pickup->delivery_tracking_status);
        $this->assertTrue($pickupFollowUpAt->equalTo($pickup->delivery_manual_follow_up_at));
        $this->assertTrue($pickupDeadline->equalTo($pickup->pickup_deadline_at));
    }

    public function test_scheduled_command_reconciles_due_follow_up_idempotently(): void
    {
        $order = Order::factory()->awaitingReshipmentPayment()->create([
            'reshipment_payment_due_at' => now(),
        ]);

        $this->assertSame(0, Artisan::call('orders:reconcile-fulfillment'));
        $this->assertStringContainsString('Seguimientos reconciliados: 1', Artisan::output());
        $this->assertSame(DeliveryTrackingStatus::ManualFollowUp, $order->refresh()->delivery_tracking_status);

        $this->assertSame(0, Artisan::call('orders:reconcile-fulfillment'));
        $this->assertStringContainsString('Seguimientos reconciliados: 0', Artisan::output());
    }

    public function test_attempt_database_constraints_protect_operation_and_sequence_uniqueness(): void
    {
        $order = Order::factory()->shipped()->create();
        $first = DeliveryAttempt::factory()->for($order)->create();

        foreach ([
            ['operation_token' => $first->operation_token, 'attempt_number' => 2],
            ['operation_token' => (string) Str::uuid(), 'attempt_number' => 1],
        ] as $attributes) {
            try {
                DeliveryAttempt::factory()->for($order)->create($attributes);
                $this->fail('La restriccion unica debio rechazar el duplicado.');
            } catch (QueryException) {
                $this->assertDatabaseCount('delivery_attempts', 1);
            }
        }
    }

    public function test_attempt_history_is_immutable_and_keeps_actor_snapshots_after_user_deletion(): void
    {
        $order = Order::factory()->shipped()->create();
        $attempt = $this->customerIncident($order, 'El cliente no se encontraba disponible.');

        DB::table('users')->where('id', $this->admin->id)->delete();
        $attempt->refresh();
        $this->assertNull($attempt->recorded_by_id);
        $this->assertNull($attempt->recordedBy);
        $this->assertSame('Maria Operaciones', $attempt->recorded_by_name);
        $this->assertSame('operaciones@example.test', $attempt->recorded_by_email);

        try {
            $attempt->update(['reason' => 'Historia reescrita']);
            $this->fail('El historial no debe poder modificarse.');
        } catch (LogicException) {
            $this->assertSame('El cliente no se encontraba disponible.', $attempt->refresh()->reason);
        }

        try {
            $attempt->delete();
            $this->fail('El historial no debe poder eliminarse.');
        } catch (LogicException) {
            $this->assertDatabaseHas('delivery_attempts', ['id' => $attempt->id]);
        }
    }

    private function customerIncident(Order $order, string $reason): DeliveryAttempt
    {
        return $this->record(
            $order,
            DeliveryAttemptResult::Incident,
            DeliveryAttemptAttribution::Customer,
            $reason,
        );
    }

    private function record(
        Order $order,
        DeliveryAttemptResult $result,
        DeliveryAttemptAttribution $attribution,
        ?string $reason,
        ?string $token = null,
        mixed $occurredAt = null,
    ): DeliveryAttempt {
        return $this->fulfillment->recordDeliveryAttempt(
            $order,
            $this->admin,
            $token ?? (string) Str::uuid(),
            $result,
            $attribution,
            'Transportista asignado',
            $reason,
            $occurredAt ?? now(),
        );
    }
}
