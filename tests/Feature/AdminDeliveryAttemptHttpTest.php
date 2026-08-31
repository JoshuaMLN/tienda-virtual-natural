<?php

namespace Tests\Feature;

use App\Enums\AdminFulfillmentFilter;
use App\Enums\DeliveryAttemptAttribution;
use App\Enums\DeliveryAttemptResult;
use App\Enums\DeliveryStatus;
use App\Enums\DeliveryTrackingStatus;
use App\Enums\OrderStatus;
use App\Models\DeliveryAttempt;
use App\Models\Order;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class AdminDeliveryAttemptHttpTest extends TestCase
{
    use RefreshDatabase;

    private User $adminUser;

    protected function setUp(): void
    {
        parent::setUp();

        $this->adminUser = User::factory()->admin()->create();
        $this->actingAs($this->adminUser);
        CarbonImmutable::setTestNow('2026-07-31 10:00:00');
    }

    protected function tearDown(): void
    {
        CarbonImmutable::setTestNow();

        parent::tearDown();
    }

    public function test_shipped_order_detail_exposes_result_form_and_auditable_attempts(): void
    {
        $order = Order::factory()->shipped()->create([
            'delivery_attempts_per_cycle' => 3,
            'delivery_max_automatic_cycles' => 2,
        ]);
        DeliveryAttempt::factory()->for($order)->create([
            'recorded_by_id' => $this->adminUser->id,
            'recorded_by_name' => $this->adminUser->name,
            'recorded_by_email' => $this->adminUser->email,
        ]);

        $this->get(route('admin.orders.show', $order->code))
            ->assertOk()
            ->assertSee('Registrar resultado de entrega')
            ->assertSee('Ciclo actual')
            ->assertSee('1 de 2')
            ->assertSee('Intentos consumidos')
            ->assertSee('1 de 3')
            ->assertSee('Cliente')
            ->assertSee('No atribuible')
            ->assertSee('No se encontro a una persona que pudiera recibir el pedido.')
            ->assertSee(route('admin.orders.delivery-attempts.store', $order->code), false)
            ->assertDontSee('Confirmar entrega');
    }

    public function test_admin_can_register_an_incident_and_return_to_the_preserved_filter(): void
    {
        $order = Order::factory()->shipped()->create();
        $filter = AdminFulfillmentFilter::ManualFollowUp->value;

        $this->post(route('admin.orders.delivery-attempts.store', $order->code), [
            'operation_token' => (string) Str::uuid(),
            'result' => DeliveryAttemptResult::Incident->value,
            'attribution' => DeliveryAttemptAttribution::Carrier->value,
            'responsible_name' => 'Transporte Lima 01',
            'attempt_reason' => 'El vehiculo presento una averia durante el recorrido.',
            'occurred_at' => '2026-07-31T10:00',
            'return' => [
                'seguimiento' => $filter,
                'page' => 2,
            ],
        ])->assertRedirect(route('admin.orders.show', [
            'order' => $order->code,
            'seguimiento' => $filter,
            'page' => 2,
        ]))->assertSessionHas('success', 'La incidencia de entrega fue registrada.');

        $this->assertDatabaseHas('delivery_attempts', [
            'order_id' => $order->id,
            'result' => DeliveryAttemptResult::Incident->value,
            'attribution' => DeliveryAttemptAttribution::Carrier->value,
            'consumes_attempt' => false,
            'responsible_name' => 'Transporte Lima 01',
        ]);
    }

    public function test_incident_requires_attribution_reason_responsible_and_valid_date(): void
    {
        $order = Order::factory()->shipped()->create();

        $this->from(route('admin.orders.show', $order->code))
            ->post(route('admin.orders.delivery-attempts.store', $order->code), [
                'operation_token' => (string) Str::uuid(),
                'result' => DeliveryAttemptResult::Incident->value,
                'occurred_at' => 'fecha-invalida',
            ])
            ->assertRedirect(route('admin.orders.show', $order->code))
            ->assertSessionHasErrors([
                'attribution',
                'responsible_name',
                'attempt_reason',
                'occurred_at',
            ]);

        $this->assertDatabaseCount('delivery_attempts', 0);
    }

    public function test_delivered_result_completes_the_order_and_removes_the_form(): void
    {
        $order = Order::factory()->shipped()->create();

        $this->post(route('admin.orders.delivery-attempts.store', $order->code), [
            'operation_token' => (string) Str::uuid(),
            'result' => DeliveryAttemptResult::Delivered->value,
            'attribution' => DeliveryAttemptAttribution::Customer->value,
            'responsible_name' => 'Transporte Lima 01',
            'occurred_at' => '2026-07-31T10:00',
        ])->assertRedirect(route('admin.orders.show', $order->code))
            ->assertSessionHas('success');

        $order->refresh();
        $this->assertSame(OrderStatus::Completed, $order->order_status);
        $this->assertSame(DeliveryStatus::Delivered, $order->delivery_status);
        $this->assertSame(DeliveryTrackingStatus::Completed, $order->delivery_tracking_status);

        $this->get(route('admin.orders.show', $order->code))
            ->assertOk()
            ->assertSee('Entregado')
            ->assertDontSee('Registrar resultado de entrega');
    }

    public function test_customer_and_guest_cannot_register_delivery_attempts(): void
    {
        $order = Order::factory()->shipped()->create();
        $payload = [
            'operation_token' => (string) Str::uuid(),
            'result' => DeliveryAttemptResult::Delivered->value,
            'responsible_name' => 'Transportista',
            'occurred_at' => '2026-07-31T10:00',
        ];

        $this->actingAs(User::factory()->create())
            ->post(route('admin.orders.delivery-attempts.store', $order->code), $payload)
            ->assertForbidden();

        auth()->logout();
        $this->post(route('admin.orders.delivery-attempts.store', $order->code), $payload)
            ->assertRedirect(route('admin.login'));

        $this->assertDatabaseCount('delivery_attempts', 0);
    }

    public function test_fulfillment_filters_return_only_matching_orders(): void
    {
        $dueSoon = Order::factory()->readyForPickup()->create([
            'code' => 'PED-2026-000701',
            'pickup_deadline_at' => now()->addHours(24),
        ]);
        $overdue = Order::factory()->readyForPickup()->create([
            'code' => 'PED-2026-000702',
            'pickup_deadline_at' => now(),
        ]);
        $reshipment = Order::factory()->awaitingReshipmentPayment()->create([
            'code' => 'PED-2026-000703',
        ]);
        $manual = Order::factory()->manualFollowUp()->create([
            'code' => 'PED-2026-000704',
        ]);

        foreach ([
            AdminFulfillmentFilter::PickupDueSoon->value => $dueSoon,
            AdminFulfillmentFilter::PickupOverdue->value => $overdue,
            AdminFulfillmentFilter::ReshipmentPending->value => $reshipment,
            AdminFulfillmentFilter::ManualFollowUp->value => $manual,
        ] as $filter => $expected) {
            $response = $this->get(route('admin.orders.index', ['seguimiento' => $filter]))
                ->assertOk()
                ->assertSee($expected->code);

            foreach ([$dueSoon, $overdue, $reshipment, $manual] as $order) {
                if (! $order->is($expected)) {
                    $response->assertDontSee($order->code);
                }
            }
        }
    }

    public function test_pickup_detail_shows_frozen_deadline_and_never_offers_delivery_attempt_form(): void
    {
        $order = Order::factory()->readyForPickup()->create([
            'pickup_ready_at' => '2026-07-20 09:00:00',
            'pickup_deadline_at' => '2026-08-03 09:00:00',
        ]);

        $this->get(route('admin.orders.show', $order->code))
            ->assertOk()
            ->assertSee('Disponible desde')
            ->assertSee('Fecha limite de recojo')
            ->assertSee('3 de agosto de 2026')
            ->assertDontSee('Registrar resultado de entrega');
    }
}
