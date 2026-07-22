<?php

namespace Tests\Feature\Account;

use App\Enums\DeliveryMethod;
use App\Enums\DeliveryStatus;
use App\Enums\FiscalDocumentType;
use App\Enums\FiscalIdentityDocumentType;
use App\Enums\OrderHistoryDomain;
use App\Enums\OrderStatus;
use App\Enums\PaymentStatus;
use App\Enums\TaxAffectation;
use App\Models\Brand;
use App\Models\Category;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\OrderStatusHistory;
use App\Models\Product;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Testing\TestResponse;
use Tests\TestCase;

class CustomerOrderDetailHttpTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config()->set('app.locale', 'es');
        config()->set('app.timezone', 'America/Lima');
        CarbonImmutable::setTestNow('2026-07-22 10:00:00', 'America/Lima');
    }

    protected function tearDown(): void
    {
        CarbonImmutable::setTestNow();

        parent::tearDown();
    }

    public function test_detail_displays_a_descriptive_order_date_in_lima_time(): void
    {
        $customer = User::factory()->create();
        $order = $this->order($customer, 1, [
            'created_at' => CarbonImmutable::parse('2026-07-22 08:35:00', 'America/Lima'),
        ]);

        $this->actingAs($customer)
            ->get(route('account.orders.show', $order->code))
            ->assertOk()
            ->assertSee('Realizado el 22 de julio de 2026 a las 08:35 a. m.');
    }

    public function test_detail_renders_item_snapshots_discounts_and_tax_amounts(): void
    {
        $customer = User::factory()->create();
        $order = $this->order($customer, 2, [
            'products_subtotal_cents' => 25_500,
            'discount_cents' => 1_000,
            'shipping_fee_cents' => 500,
            'shipping_net_value_cents' => 424,
            'shipping_tax_cents' => 76,
            'taxable_value_cents' => 9_746,
            'exempt_value_cents' => 8_000,
            'unaffected_value_cents' => 5_500,
            'net_value_cents' => 23_246,
            'tax_cents' => 1_754,
            'total_cents' => 25_000,
        ]);

        OrderItem::factory()->for($order)->create([
            'product_id' => null,
            'product_name' => 'Omega Snapshot',
            'product_sku' => 'SNAP-OMEGA-01',
            'product_presentation' => '120 capsulas',
            'quantity' => 2,
            'tax_affectation' => TaxAffectation::Taxed,
            'unit_price_cents' => 6_000,
            'gross_total_cents' => 12_000,
            'discount_cents' => 1_000,
            'net_value_cents' => 9_322,
            'tax_cents' => 1_678,
            'total_cents' => 11_000,
        ]);
        OrderItem::factory()->exempt()->for($order)->create([
            'product_id' => null,
            'product_name' => 'Infusion Exonerada',
            'product_sku' => 'SNAP-EXO-02',
            'product_presentation' => '20 sobres',
            'unit_price_cents' => 8_000,
            'gross_total_cents' => 8_000,
            'total_cents' => 8_000,
        ]);
        OrderItem::factory()->unaffected()->for($order)->create([
            'product_id' => null,
            'product_name' => 'Producto Inafecto',
            'product_sku' => 'SNAP-INA-03',
            'product_presentation' => null,
            'unit_price_cents' => 5_500,
            'gross_total_cents' => 5_500,
            'net_value_cents' => 5_500,
            'total_cents' => 5_500,
        ]);

        $response = $this->actingAs($customer)
            ->get(route('account.orders.show', $order->code));

        $response->assertOk()
            ->assertSee('3 productos')
            ->assertSee('4 unidades')
            ->assertSee('Omega Snapshot')
            ->assertSee('SKU: SNAP-OMEGA-01')
            ->assertSee('120 capsulas')
            ->assertSee('Cantidad: 2')
            ->assertSee('Precio unitario: S/ 60.00')
            ->assertSee('Antes S/ 120.00')
            ->assertSee('Descuento S/ 10.00')
            ->assertSee('S/ 110.00')
            ->assertSee('Infusion Exonerada')
            ->assertSee('Exonerado')
            ->assertSee('Producto Inafecto')
            ->assertSee('Inafecto')
            ->assertSee('Valor gravado')
            ->assertSee('S/ 97.46')
            ->assertSee('Valor exonerado')
            ->assertSee('S/ 80.00')
            ->assertSee('Valor inafecto')
            ->assertSee('S/ 55.00')
            ->assertSee('IGV incluido')
            ->assertSee('S/ 17.54')
            ->assertSee('S/ 250.00');
    }

    public function test_item_snapshot_remains_unchanged_when_the_current_product_changes(): void
    {
        $customer = User::factory()->create();
        $order = $this->order($customer, 3);
        $product = Product::factory()->create([
            'name' => 'Nombre original del catalogo',
            'slug' => 'nombre-original-catalogo',
            'sku' => 'SKU-CURRENT-OLD',
            'price' => '79.90',
        ]);

        OrderItem::factory()->for($order)->for($product)->create([
            'product_name' => 'Nombre historico comprado',
            'product_sku' => 'SKU-SNAPSHOT-001',
            'product_presentation' => '90 capsulas',
            'unit_price_cents' => 7_990,
            'gross_total_cents' => 7_990,
            'net_value_cents' => 6_771,
            'tax_cents' => 1_219,
            'total_cents' => 7_990,
        ]);

        $product->update([
            'name' => 'Nombre actual modificado',
            'slug' => 'nombre-actual-modificado',
            'sku' => 'SKU-CURRENT-NEW',
            'price' => '99.90',
        ]);

        $this->actingAs($customer)
            ->get(route('account.orders.show', $order->code))
            ->assertOk()
            ->assertSee('Nombre historico comprado')
            ->assertSee('SKU: SKU-SNAPSHOT-001')
            ->assertSee('90 capsulas')
            ->assertSee('S/ 79.90')
            ->assertDontSee('Nombre actual modificado')
            ->assertDontSee('SKU-CURRENT-NEW')
            ->assertDontSee('S/ 99.90');
    }

    public function test_catalog_link_is_only_rendered_when_the_current_product_is_visible(): void
    {
        $customer = User::factory()->create();
        $order = $this->order($customer, 4);
        $activeCategory = Category::factory()->create();
        $inactiveCategory = Category::factory()->inactive()->create();
        $activeBrand = $this->brand('marca-activa', true);
        $inactiveBrand = $this->brand('marca-inactiva', false);

        $products = [
            'Producto visible' => Product::factory()->create([
                'category_id' => $activeCategory->id,
                'brand_id' => $activeBrand->id,
                'slug' => 'producto-visible',
            ]),
            'Producto inactivo' => Product::factory()->inactive()->create([
                'category_id' => $activeCategory->id,
                'slug' => 'producto-inactivo',
            ]),
            'Categoria inactiva' => Product::factory()->create([
                'category_id' => $inactiveCategory->id,
                'slug' => 'categoria-inactiva',
            ]),
            'Marca inactiva' => Product::factory()->create([
                'category_id' => $activeCategory->id,
                'brand_id' => $inactiveBrand->id,
                'slug' => 'marca-inactiva',
            ]),
            'Producto no publicado' => Product::factory()->unpublished()->create([
                'category_id' => $activeCategory->id,
                'slug' => 'producto-no-publicado',
            ]),
        ];

        foreach ($products as $snapshotName => $product) {
            OrderItem::factory()->for($order)->for($product)->create([
                'product_name' => $snapshotName,
            ]);
        }

        OrderItem::factory()->for($order)->create([
            'product_id' => null,
            'product_name' => 'Producto eliminado',
        ]);

        $response = $this->actingAs($customer)
            ->get(route('account.orders.show', $order->code));

        $response->assertOk()
            ->assertSee('aria-label="Ver Producto visible"', false)
            ->assertSee('href="'.route('shop.product', 'producto-visible').'"', false);

        foreach (array_keys($products) as $snapshotName) {
            if ($snapshotName === 'Producto visible') {
                continue;
            }

            $response->assertDontSee('aria-label="Ver '.$snapshotName.'"', false);
        }

        $response->assertDontSee('aria-label="Ver Producto eliminado"', false);
    }

    public function test_receipt_displays_a_masked_dni_without_leaking_the_full_document(): void
    {
        $customer = User::factory()->create();
        $order = $this->order($customer, 5, [
            'fiscal_document_type' => FiscalDocumentType::Receipt,
            'fiscal_identity_document_type' => FiscalIdentityDocumentType::Dni,
            'fiscal_identity_document_number' => '12345678',
            'fiscal_first_names' => 'Maria Elena',
            'fiscal_last_names' => 'Perez Ruiz',
        ]);

        $this->actingAs($customer)
            ->get(route('account.orders.show', $order->code))
            ->assertOk()
            ->assertSee('Boleta')
            ->assertSee('Maria Elena Perez Ruiz')
            ->assertSee('DNI')
            ->assertSee('****5678')
            ->assertDontSee('12345678');
    }

    public function test_invoice_displays_a_masked_ruc_without_leaking_the_full_document(): void
    {
        $customer = User::factory()->create();
        $order = $this->order($customer, 6, [
            'fiscal_document_type' => FiscalDocumentType::Invoice,
            'fiscal_identity_document_type' => FiscalIdentityDocumentType::Ruc,
            'fiscal_identity_document_number' => '20131312955',
            'fiscal_first_names' => null,
            'fiscal_last_names' => null,
            'fiscal_business_name' => 'Natural Peru SAC',
            'fiscal_address' => 'Av. Empresa 456, Lima',
        ]);

        $this->actingAs($customer)
            ->get(route('account.orders.show', $order->code))
            ->assertOk()
            ->assertSee('Factura')
            ->assertSee('Natural Peru SAC')
            ->assertSee('RUC')
            ->assertSee('*******2955')
            ->assertSee('Av. Empresa 456, Lima')
            ->assertDontSee('20131312955');
    }

    public function test_home_delivery_displays_snapshot_address_and_pending_reservation_without_an_estimate(): void
    {
        $customer = User::factory()->create();
        $order = $this->order($customer, 7, [
            'delivery_method' => DeliveryMethod::HomeDelivery,
            'delivery_recipient_name' => 'Ana Receptora',
            'delivery_phone' => '987654321',
            'delivery_department' => 'Lima',
            'delivery_province' => 'Callao',
            'delivery_district' => 'La Perla',
            'delivery_address' => 'Jr. Los Alamos 321',
            'delivery_reference' => 'Puerta verde',
            'reservation_expires_at' => now()->addMinutes(15),
            'delivery_estimated_from' => now()->addDay()->toDateString(),
            'delivery_estimated_to' => now()->addDays(2)->toDateString(),
            'paid_at' => null,
        ]);

        $this->actingAs($customer)
            ->get(route('account.orders.show', $order->code))
            ->assertOk()
            ->assertSee('Entrega a domicilio')
            ->assertSee('Ana Receptora')
            ->assertSee('987654321')
            ->assertSee('Jr. Los Alamos 321')
            ->assertSee('La Perla, Callao, Lima')
            ->assertSee('Puerta verde')
            ->assertSee('Reserva vigente hasta el 22 de julio de 2026 a las 10:15 a. m.')
            ->assertDontSee('Entrega estimada');
    }

    public function test_paid_pickup_displays_pickup_snapshot_and_estimate_but_not_a_reservation(): void
    {
        $customer = User::factory()->create();
        $order = $this->order($customer, 8, [
            'order_status' => OrderStatus::Processing,
            'payment_status' => PaymentStatus::Paid,
            'delivery_method' => DeliveryMethod::Pickup,
            'pickup_address' => 'Av. Javier Prado 1234, San Isidro',
            'paid_at' => now(),
            'delivery_estimated_from' => '2026-07-23',
            'delivery_estimated_to' => '2026-07-24',
            'reservation_expires_at' => now()->addMinutes(15),
        ]);

        $this->actingAs($customer)
            ->get(route('account.orders.show', $order->code))
            ->assertOk()
            ->assertSee('Recojo en tienda')
            ->assertSee('Av. Javier Prado 1234, San Isidro')
            ->assertSee('Recojo disponible del 23 al 24 de julio de 2026')
            ->assertDontSee('Reserva vigente hasta');
    }

    public function test_reservation_is_hidden_when_the_order_is_no_longer_pending(): void
    {
        $customer = User::factory()->create();
        $order = $this->order($customer, 9, [
            'order_status' => OrderStatus::Cancelled,
            'reservation_expires_at' => now()->addMinutes(15),
            'cancelled_at' => now(),
        ]);

        $this->actingAs($customer)
            ->get(route('account.orders.show', $order->code))
            ->assertOk()
            ->assertDontSee('Reserva vigente hasta');
    }

    public function test_home_delivery_timeline_contains_only_curated_customer_events(): void
    {
        $customer = User::factory()->create();
        $actor = User::factory()->admin()->create(['email' => 'internal-actor@example.test']);
        $order = $this->order($customer, 10, [
            'created_at' => now()->subMinutes(10),
            'order_status' => OrderStatus::Completed,
            'payment_status' => PaymentStatus::Refunded,
            'delivery_status' => DeliveryStatus::Delivered,
        ]);

        $this->history($order, OrderHistoryDomain::Payment, PaymentStatus::Pending->value, PaymentStatus::Paid->value, 1, [
            'actor_id' => $actor->id,
            'actor_name' => 'Administrador Interno',
            'actor_email' => $actor->email,
            'reason' => 'INTERNAL_REASON_X7',
            'metadata' => ['gateway_id' => 'movement-id-984321'],
        ]);
        $this->history($order, OrderHistoryDomain::Order, OrderStatus::PendingPayment->value, OrderStatus::Processing->value, 2);
        $this->history($order, OrderHistoryDomain::Delivery, DeliveryStatus::Pending->value, DeliveryStatus::Preparing->value, 3);
        $this->history($order, OrderHistoryDomain::Delivery, DeliveryStatus::Preparing->value, DeliveryStatus::Shipped->value, 4);
        $this->history($order, OrderHistoryDomain::Delivery, DeliveryStatus::Shipped->value, DeliveryStatus::Delivered->value, 5);
        $this->history($order, OrderHistoryDomain::Payment, PaymentStatus::Paid->value, PaymentStatus::Refunded->value, 6);
        $this->history($order, OrderHistoryDomain::Reservation, 'pending', 'active', 7, [
            'reason' => 'RESERVATION_INTERNAL_REASON',
            'metadata' => ['reservation_id' => 'reservation-secret-7744'],
        ]);

        $response = $this->actingAs($customer)
            ->get(route('account.orders.show', $order->code));

        $response->assertOk();
        $this->assertSame([
            'Pedido creado',
            'Pago confirmado',
            'Pedido en preparacion',
            'Pedido en camino',
            'Pedido entregado',
            'Pago reembolsado',
        ], $this->timelineTitles($response));

        $response->assertSee('22 de julio de 2026 a las 09:50 a. m.')
            ->assertSee('22 de julio de 2026 a las 10:06 a. m.')
            ->assertDontSee('internal-actor@example.test')
            ->assertDontSee('Administrador Interno')
            ->assertDontSee('INTERNAL_REASON_X7')
            ->assertDontSee('movement-id-984321')
            ->assertDontSee('RESERVATION_INTERNAL_REASON')
            ->assertDontSee('reservation-secret-7744');
    }

    public function test_pickup_timeline_uses_ready_and_picked_up_customer_events(): void
    {
        $customer = User::factory()->create();
        $order = $this->order($customer, 11, [
            'delivery_method' => DeliveryMethod::Pickup,
            'delivery_status' => DeliveryStatus::PickedUp,
            'order_status' => OrderStatus::Completed,
        ]);

        $this->history($order, OrderHistoryDomain::Delivery, DeliveryStatus::Preparing->value, DeliveryStatus::ReadyForPickup->value, 1);
        $this->history($order, OrderHistoryDomain::Delivery, DeliveryStatus::ReadyForPickup->value, DeliveryStatus::PickedUp->value, 2);

        $response = $this->actingAs($customer)
            ->get(route('account.orders.show', $order->code));

        $response->assertOk();
        $this->assertSame([
            'Pedido creado',
            'Listo para recoger',
            'Pedido recogido',
        ], $this->timelineTitles($response));
    }

    public function test_cancelled_and_expired_orders_expose_their_curated_terminal_event_once(): void
    {
        $customer = User::factory()->create();
        $cancelled = $this->order($customer, 12, [
            'order_status' => OrderStatus::Cancelled,
        ]);
        $expired = $this->order($customer, 13, [
            'order_status' => OrderStatus::Expired,
            'payment_status' => PaymentStatus::Expired,
        ]);

        $this->history($cancelled, OrderHistoryDomain::Order, OrderStatus::PendingPayment->value, OrderStatus::Cancelled->value, 1);
        $this->history($cancelled, OrderHistoryDomain::Delivery, DeliveryStatus::Pending->value, DeliveryStatus::Cancelled->value, 2);
        $this->history($expired, OrderHistoryDomain::Payment, PaymentStatus::Pending->value, PaymentStatus::Expired->value, 1);
        $this->history($expired, OrderHistoryDomain::Order, OrderStatus::PendingPayment->value, OrderStatus::Expired->value, 2);

        $cancelledResponse = $this->actingAs($customer)
            ->get(route('account.orders.show', $cancelled->code));
        $expiredResponse = $this->get(route('account.orders.show', $expired->code));

        $cancelledResponse->assertOk();
        $expiredResponse->assertOk();
        $this->assertSame(['Pedido creado', 'Pedido cancelado'], $this->timelineTitles($cancelledResponse));
        $this->assertSame(['Pedido creado', 'Pedido vencido'], $this->timelineTitles($expiredResponse));
    }

    public function test_foreign_detail_returns_404_and_own_detail_contains_responsive_markers(): void
    {
        $customer = User::factory()->create();
        $otherCustomer = User::factory()->create();
        $ownOrder = $this->order($customer, 14);
        $foreignOrder = $this->order($otherCustomer, 15);

        $this->actingAs($customer)
            ->get(route('account.orders.show', $ownOrder->code))
            ->assertOk()
            ->assertSee('customer-order-detail-grid', false)
            ->assertSee('customer-order-detail-column-primary', false)
            ->assertSee('customer-order-detail-column-secondary', false)
            ->assertSee('customer-order-products p-3 p-lg-4', false)
            ->assertSee('customer-order-timeline-card p-3 p-lg-4', false);

        $this->get(route('account.orders.show', $foreignOrder->code))
            ->assertNotFound();
    }

    /** @param array<string, mixed> $attributes */
    private function order(User $customer, int $sequence, array $attributes = []): Order
    {
        return Order::factory()->for($customer)->create(array_merge([
            'code' => sprintf('PED-2026-%06d', $sequence),
            'sequence_year' => 2026,
            'sequence_number' => $sequence,
            'order_status' => OrderStatus::PendingPayment,
            'payment_status' => PaymentStatus::Pending,
            'delivery_status' => DeliveryStatus::Pending,
            'reservation_expires_at' => null,
        ], $attributes));
    }

    /** @param array<string, mixed> $attributes */
    private function history(
        Order $order,
        OrderHistoryDomain $domain,
        string $from,
        string $to,
        int $minutesAfterNow,
        array $attributes = [],
    ): OrderStatusHistory {
        return OrderStatusHistory::factory()->for($order)->create(array_merge([
            'domain' => $domain,
            'from_status' => $from,
            'to_status' => $to,
            'created_at' => now()->addMinutes($minutesAfterNow),
        ], $attributes));
    }

    /** @return list<string> */
    private function timelineTitles(TestResponse $response): array
    {
        return array_map(
            fn (array $item): string => $item['event']->title,
            $response->viewData('detail')['timeline'],
        );
    }

    private function brand(string $slug, bool $active): Brand
    {
        return Brand::query()->create([
            'name' => str($slug)->replace('-', ' ')->title()->toString(),
            'slug' => $slug,
            'is_active' => $active,
            'sort_order' => 0,
        ]);
    }
}
