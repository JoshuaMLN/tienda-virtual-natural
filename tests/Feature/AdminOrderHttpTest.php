<?php

namespace Tests\Feature;

use App\Enums\DeliveryMethod;
use App\Enums\DeliveryStatus;
use App\Enums\FiscalDeliveryStatus;
use App\Enums\OrderHistoryDomain;
use App\Enums\OrderNotificationStatus;
use App\Enums\OrderNotificationType;
use App\Enums\OrderStatus;
use App\Enums\PaymentStatus;
use App\Enums\ReservationStatus;
use App\Models\FiscalDocument;
use App\Models\FiscalDocumentDelivery;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\OrderNotificationDelivery;
use App\Models\OrderStatusHistory;
use App\Models\StockReservation;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\AuthenticatesAdmins;
use Tests\TestCase;

class AdminOrderHttpTest extends TestCase
{
    use AuthenticatesAdmins;
    use RefreshDatabase;

    public function test_admin_order_index_uses_real_orders_sorted_newest_first(): void
    {
        $older = Order::factory()->create([
            'code' => 'PED-2026-000101',
            'customer_name' => 'Cliente Anterior',
            'created_at' => now()->subDay(),
        ]);
        $newer = Order::factory()->paid()->create([
            'code' => 'PED-2026-000102',
            'customer_name' => 'Cliente Reciente',
            'created_at' => now(),
        ]);
        OrderItem::factory()->for($older)->create(['quantity' => 1]);
        OrderItem::factory()->for($newer)->create(['quantity' => 3]);

        $response = $this->get(route('admin.orders.index'));

        $response
            ->assertOk()
            ->assertHeader('Cache-Control', 'no-store, private')
            ->assertSeeInOrder([$newer->code, $older->code])
            ->assertSee('Cliente Reciente')
            ->assertSee('3 unidades')
            ->assertSee('Pagado')
            ->assertDontSee('VN-2024-000123');
    }

    public function test_admin_can_search_orders_by_code_customer_email_phone_or_fiscal_document(): void
    {
        $matching = Order::factory()->invoice()->create([
            'code' => 'PED-2026-000210',
            'customer_name' => 'Rosa Quispe',
            'customer_email' => 'rosa.pedidos@example.test',
            'customer_phone' => '987654321',
            'fiscal_identity_document_number' => '20131312955',
            'fiscal_business_name' => 'Comercial Rosa SAC',
        ]);
        $other = Order::factory()->create([
            'code' => 'PED-2026-000211',
            'customer_name' => 'Otro Cliente',
            'customer_email' => 'otro@example.test',
        ]);

        foreach ([
            '000210',
            'Rosa Quispe',
            'rosa.pedidos@example.test',
            '987654321',
            '20131312955',
            'Comercial Rosa',
        ] as $search) {
            $this->get(route('admin.orders.index', ['q' => $search]))
                ->assertOk()
                ->assertSee($matching->code)
                ->assertDontSee($other->code);
        }
    }

    public function test_admin_can_combine_technical_modality_and_creation_date_filters(): void
    {
        $matching = Order::factory()
            ->processing()
            ->pickup()
            ->create([
                'code' => 'PED-2026-000301',
                'delivery_status' => DeliveryStatus::ReadyForPickup,
                'created_at' => '2026-07-20 11:00:00',
            ]);
        Order::factory()->processing()->create([
            'code' => 'PED-2026-000302',
            'delivery_status' => DeliveryStatus::Shipped,
            'created_at' => '2026-07-20 12:00:00',
        ]);
        Order::factory()->pickup()->create([
            'code' => 'PED-2026-000303',
            'created_at' => '2026-07-19 12:00:00',
        ]);

        $this->get(route('admin.orders.index', [
            'estado_pedido' => OrderStatus::Processing->value,
            'estado_pago' => PaymentStatus::Paid->value,
            'estado_entrega' => DeliveryStatus::ReadyForPickup->value,
            'modalidad' => DeliveryMethod::Pickup->value,
            'desde' => '2026-07-20',
            'hasta' => '2026-07-20',
        ]))
            ->assertOk()
            ->assertSee($matching->code)
            ->assertDontSee('PED-2026-000302')
            ->assertDontSee('PED-2026-000303');
    }

    public function test_admin_order_filters_reject_invalid_enums_and_date_ranges(): void
    {
        $this->from(route('admin.orders.index'))
            ->get(route('admin.orders.index', [
                'estado_pago' => 'inventado',
                'desde' => '2026-07-21',
                'hasta' => '2026-07-20',
            ]))
            ->assertRedirect(route('admin.orders.index'))
            ->assertSessionHasErrors(['estado_pago', 'hasta']);
    }

    public function test_admin_order_pagination_preserves_filters_and_detail_return_query(): void
    {
        Order::factory()
            ->count(16)
            ->sequence(fn ($sequence): array => [
                'customer_name' => 'Cliente Paginado '.($sequence->index + 1),
                'created_at' => now()->subMinutes($sequence->index),
            ])
            ->create();

        $response = $this->get(route('admin.orders.index', ['q' => 'Cliente Paginado']));

        $response
            ->assertOk()
            ->assertSee('q=Cliente%20Paginado', false)
            ->assertSee('page=2', false);

        $firstOrder = Order::query()->latest('created_at')->latest('id')->firstOrFail();

        $this->get(route('admin.orders.show', [
            'order' => $firstOrder->code,
            'q' => 'Cliente Paginado',
            'page' => 2,
        ]))
            ->assertOk()
            ->assertSee(
                'href="'.e(route('admin.orders.index', [
                    'q' => 'Cliente Paginado',
                    'page' => 2,
                ])).'"',
                false,
            );
    }

    public function test_admin_order_detail_displays_complete_snapshots_and_audit_data_read_only(): void
    {
        $customer = User::factory()->create([
            'name' => 'Cuenta Actual',
            'email' => 'actual@example.test',
            'email_verified_at' => now(),
        ]);
        $order = Order::factory()
            ->for($customer)
            ->paid()
            ->invoice()
            ->create([
                'code' => 'PED-2026-000401',
                'customer_name' => 'Cliente Snapshot',
                'customer_email' => 'snapshot@example.test',
                'customer_phone' => '999888777',
                'delivery_recipient_name' => 'Receptor Snapshot',
                'delivery_address' => 'Av. Prueba 456',
                'delivery_reference' => 'Puerta verde',
                'fiscal_business_name' => 'Empresa Snapshot SAC',
                'fiscal_identity_document_number' => '20131312955',
                'fiscal_email' => 'facturas@example.test',
                'terms_document_version' => 3,
                'terms_content_fingerprint' => str_repeat('a', 64),
                'terms_accepted_at' => now()->subHour(),
                'products_subtotal_cents' => 11_800,
                'shipping_fee_cents' => 800,
                'shipping_net_value_cents' => 678,
                'shipping_tax_cents' => 122,
                'taxable_value_cents' => 10_000,
                'net_value_cents' => 10_000,
                'tax_cents' => 1_800,
                'total_cents' => 12_600,
            ]);
        $item = OrderItem::factory()->for($order)->create([
            'product_name' => 'Omega Snapshot',
            'product_sku' => 'OMEGA-HIST',
            'quantity' => 2,
            'unit_price_cents' => 5_900,
            'gross_total_cents' => 11_800,
            'net_value_cents' => 10_000,
            'tax_cents' => 1_800,
            'total_cents' => 11_800,
        ]);
        StockReservation::factory()->forOrderItem($item)->consumed()->create();
        OrderStatusHistory::factory()->for($order)->create([
            'domain' => OrderHistoryDomain::Payment,
            'from_status' => PaymentStatus::Pending->value,
            'to_status' => PaymentStatus::Paid->value,
            'actor_id' => $this->adminUser->id,
            'actor_name' => $this->adminUser->name,
            'actor_email' => $this->adminUser->email,
            'reason' => 'Confirmacion de prueba',
            'metadata' => ['provider' => 'test'],
        ]);
        OrderNotificationDelivery::factory()->sent()->for($order)->create([
            'type' => OrderNotificationType::Created,
            'status' => OrderNotificationStatus::Sent,
            'recipient_email' => 'snapshot@example.test',
        ]);
        $document = FiscalDocument::factory()->for($order)->create([
            'series' => 'F001',
            'correlative' => '00000042',
            'registrar_name' => $this->adminUser->name,
        ]);
        FiscalDocumentDelivery::factory()->for($document)->create([
            'status' => FiscalDeliveryStatus::Sent,
            'recipient_email' => 'facturas@example.test',
            'attempted_by_name' => $this->adminUser->name,
        ]);

        Model::preventLazyLoading();

        try {
            $response = $this->get(route('admin.orders.show', $order->code));
        } finally {
            Model::preventLazyLoading(false);
        }

        $response
            ->assertOk()
            ->assertHeader('Cache-Control', 'no-store, private')
            ->assertSee($order->code)
            ->assertSee('Cliente Snapshot')
            ->assertSee('actual@example.test')
            ->assertSee('Omega Snapshot')
            ->assertSee('OMEGA-HIST')
            ->assertSee('Av. Prueba 456')
            ->assertSee('Puerta verde')
            ->assertSee('Empresa Snapshot SAC')
            ->assertSee('20131312955')
            ->assertSee('Consumida')
            ->assertSee('Confirmacion de prueba')
            ->assertSee('&quot;provider&quot;: &quot;test&quot;', false)
            ->assertSee('Pedido creado')
            ->assertSee('F001-00000042')
            ->assertSee('Version aceptada')
            ->assertSee('S/ 126.00')
            ->assertSee('Operacion del pedido')
            ->assertSee('Iniciar preparacion')
            ->assertSee('Cancelar pedido')
            ->assertSee('Motivo visible para el cliente')
            ->assertDontSee('Marcar como enviado')
            ->assertDontSee('Registrar boleta')
            ->assertDontSee('Enviar comprobante');
    }

    public function test_terminal_orders_display_contextual_payment_and_delivery_statuses(): void
    {
        $expired = Order::factory()->create([
            'order_status' => OrderStatus::Expired,
            'payment_status' => PaymentStatus::Expired,
            'delivery_status' => DeliveryStatus::Pending,
        ]);
        $cancelled = Order::factory()->create([
            'order_status' => OrderStatus::Cancelled,
            'payment_status' => PaymentStatus::Pending,
            'delivery_status' => DeliveryStatus::Cancelled,
        ]);

        $this->get(route('admin.orders.index'))
            ->assertOk()
            ->assertSee('No aplica')
            ->assertSee('No realizado');

        $this->get(route('admin.orders.show', $expired->code))
            ->assertOk()
            ->assertSeeInOrder([
                'Estados del pedido',
                'Entrega',
                'No aplica',
                'El pedido vencio antes de iniciar la entrega.',
            ]);

        $this->get(route('admin.orders.show', $cancelled->code))
            ->assertOk()
            ->assertSeeInOrder([
                'Estados del pedido',
                'Pago',
                'No realizado',
                'El pedido se cancelo antes de completar el pago.',
            ]);
    }

    public function test_admin_detail_explains_a_superseded_order_communication(): void
    {
        $order = Order::factory()->create();
        OrderNotificationDelivery::factory()
            ->for($order)
            ->superseded()
            ->create([
                'type' => OrderNotificationType::Created,
                'superseded_reason' => 'El pedido fue cancelado antes de enviar esta comunicacion.',
            ]);

        $this->get(route('admin.orders.show', $order->code))
            ->assertOk()
            ->assertSee('Pedido creado')
            ->assertSee('Omitido')
            ->assertSee('El pedido fue cancelado antes de enviar esta comunicacion.')
            ->assertDontSee('Error: El pedido fue cancelado antes de enviar esta comunicacion.');
    }

    public function test_admin_history_visually_identifies_each_technical_domain(): void
    {
        $order = Order::factory()->create();

        foreach ([
            [OrderHistoryDomain::Order, OrderStatus::Confirmed->value, OrderStatus::Processing->value],
            [OrderHistoryDomain::Payment, PaymentStatus::Pending->value, PaymentStatus::Paid->value],
            [OrderHistoryDomain::Delivery, DeliveryStatus::Pending->value, DeliveryStatus::Preparing->value],
            [OrderHistoryDomain::Reservation, ReservationStatus::Active->value, ReservationStatus::Consumed->value],
        ] as [$domain, $from, $to]) {
            OrderStatusHistory::factory()->for($order)->create([
                'domain' => $domain,
                'from_status' => $from,
                'to_status' => $to,
            ]);
        }

        $this->get(route('admin.orders.show', $order->code))
            ->assertOk()
            ->assertSee('Flujos del historial')
            ->assertSee('is-domain-order', false)
            ->assertSee('is-domain-payment', false)
            ->assertSee('is-domain-delivery', false)
            ->assertSee('is-domain-reservation', false)
            ->assertSee('bi-bag-check', false)
            ->assertSee('bi-credit-card', false)
            ->assertSee('bi-truck', false)
            ->assertSee('bi-box-seam', false);
    }

    public function test_admin_groups_reservations_by_operation_and_keeps_product_details_expandable(): void
    {
        $order = Order::factory()->create();
        $items = collect([
            OrderItem::factory()->for($order)->create([
                'product_name' => 'Omega agrupado',
                'product_sku' => 'OMEGA-G',
                'quantity' => 2,
            ]),
            OrderItem::factory()->for($order)->create([
                'product_name' => 'Maca agrupada',
                'product_sku' => 'MACA-G',
                'quantity' => 1,
            ]),
            OrderItem::factory()->for($order)->create([
                'product_name' => 'Frutos agrupados',
                'product_sku' => 'FRUTOS-G',
                'quantity' => 3,
            ]),
        ]);
        $operationReference = "reservation:consume:order:{$order->id}";

        $items->each(function (OrderItem $item) use ($order, $operationReference): void {
            $reservation = StockReservation::factory()->forOrderItem($item)->consumed()->create();

            OrderStatusHistory::factory()->for($order)->create([
                'domain' => OrderHistoryDomain::Reservation,
                'from_status' => ReservationStatus::Active->value,
                'to_status' => ReservationStatus::Consumed->value,
                'reason' => 'Reserva consumida por pago confirmado',
                'metadata' => [
                    'reservation_id' => $reservation->id,
                    'order_item_id' => $item->id,
                    'product_id' => $item->product_id,
                    'quantity' => $item->quantity,
                    'operation_reference' => $operationReference,
                ],
            ]);
        });

        $response = $this->get(route('admin.orders.show', $order->code));

        $response
            ->assertOk()
            ->assertSee('3 productos')
            ->assertSee('6 unidades')
            ->assertSee('El stock se desconto definitivamente al confirmar el pago.')
            ->assertSee('Ver detalle por producto')
            ->assertSee('Se consumieron 3 reservas (6 unidades).')
            ->assertSee('Ver 3')
            ->assertSee('Omega agrupado')
            ->assertSee('Maca agrupada')
            ->assertSee('Frutos agrupados');

        $this->assertSame(
            1,
            substr_count($response->getContent(), '<li class="is-domain-reservation">'),
            'Las reservas de la misma operacion deben ocupar un solo evento del historial.',
        );
    }

    public function test_admin_reservation_summary_reports_mixed_states_without_hiding_item_detail(): void
    {
        $order = Order::factory()->create();
        $consumedItem = OrderItem::factory()->for($order)->create(['product_name' => 'Producto consumido']);
        $expiredItem = OrderItem::factory()->for($order)->create(['product_name' => 'Producto vencido']);

        StockReservation::factory()->forOrderItem($consumedItem)->consumed()->create();
        StockReservation::factory()->forOrderItem($expiredItem)->create([
            'status' => ReservationStatus::Expired,
            'expired_at' => now(),
            'release_reason' => 'Vencimiento de la reserva',
        ]);

        $this->get(route('admin.orders.show', $order->code))
            ->assertOk()
            ->assertSee('Estado mixto')
            ->assertSee('Las reservas no comparten el mismo estado.')
            ->assertSee('Producto consumido')
            ->assertSee('Producto vencido')
            ->assertSee('Consumida')
            ->assertSee('Vencida');
    }

    public function test_customer_cannot_access_admin_orders_and_unknown_code_returns_not_found(): void
    {
        $order = Order::factory()->create();
        $customer = User::factory()->create();

        $this->actingAs($customer)
            ->get(route('admin.orders.show', $order->code))
            ->assertForbidden();

        $this->actingAs($this->adminUser)
            ->get(route('admin.orders.show', 'PED-2026-999999'))
            ->assertNotFound();
    }

    public function test_dashboard_latest_orders_come_from_database(): void
    {
        $order = Order::factory()->create([
            'code' => 'PED-2026-000501',
            'customer_name' => 'Cliente Dashboard',
        ]);

        $this->get(route('admin.dashboard'))
            ->assertOk()
            ->assertSee($order->code)
            ->assertSee('Cliente Dashboard')
            ->assertDontSee('VN-2024-000123');
    }

    public function test_admin_sees_only_contextual_order_actions_for_each_fulfillment_mode(): void
    {
        $home = Order::factory()->processing()->create([
            'delivery_status' => DeliveryStatus::Preparing,
        ]);
        $pickup = Order::factory()->processing()->pickup()->create([
            'delivery_status' => DeliveryStatus::Preparing,
        ]);

        $this->get(route('admin.orders.show', $home->code))
            ->assertOk()
            ->assertSee('Marcar como enviado')
            ->assertSee('Cancelar pedido')
            ->assertDontSee('Marcar listo para recojo');

        $this->get(route('admin.orders.show', $pickup->code))
            ->assertOk()
            ->assertSee('Marcar listo para recojo')
            ->assertSee('Cancelar pedido')
            ->assertDontSee('Marcar como enviado');
    }

    public function test_admin_operation_endpoint_applies_transition_and_preserves_return_filters(): void
    {
        $order = Order::factory()->paid()->create();

        $this->patch(route('admin.orders.start-preparation', $order->code), [
            'return' => [
                'q' => 'Cliente',
                'estado_pago' => PaymentStatus::Paid->value,
                'page' => 2,
            ],
        ])
            ->assertRedirect(route('admin.orders.show', [
                'order' => $order->code,
                'q' => 'Cliente',
                'estado_pago' => PaymentStatus::Paid->value,
                'page' => 2,
            ]))
            ->assertSessionHas('success', 'La preparacion del pedido fue iniciada.');

        $order->refresh();
        $this->assertSame(OrderStatus::Processing, $order->order_status);
        $this->assertSame(DeliveryStatus::Preparing, $order->delivery_status);
    }

    public function test_cancel_endpoint_validates_reason_before_changing_any_state(): void
    {
        $order = Order::factory()->create();

        $this->from(route('admin.orders.show', $order->code))
            ->patch(route('admin.orders.cancel', $order->code), ['reason' => '  '])
            ->assertRedirect(route('admin.orders.show', $order->code))
            ->assertSessionHasErrors('reason');

        $this->assertSame(OrderStatus::PendingPayment, $order->refresh()->order_status);
        $this->get(route('admin.orders.show', $order->code))
            ->assertOk()
            ->assertSee('admin-order-action-cancel', false);
    }

    public function test_order_operation_routes_require_authentication_and_admin_role(): void
    {
        $order = Order::factory()->paid()->create();
        $customer = User::factory()->create();

        auth()->logout();

        $this->patch(route('admin.orders.start-preparation', $order->code))
            ->assertRedirect(route('admin.login'));

        $this->actingAs($customer)
            ->patch(route('admin.orders.start-preparation', $order->code))
            ->assertForbidden();

        $this->assertSame(OrderStatus::Confirmed, $order->refresh()->order_status);
    }
}
