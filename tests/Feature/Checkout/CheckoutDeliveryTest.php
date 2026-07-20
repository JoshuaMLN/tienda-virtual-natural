<?php

namespace Tests\Feature\Checkout;

use App\Enums\DeliveryMethod;
use App\Models\CustomerAddress;
use App\Models\DeliveryDistrict;
use App\Models\NonWorkingDay;
use App\Models\Product;
use App\Models\Setting;
use App\Models\User;
use App\Support\Cart\CartService;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CheckoutDeliveryTest extends TestCase
{
    use RefreshDatabase;

    protected function tearDown(): void
    {
        CarbonImmutable::setTestNow();
        Setting::clearLocalCache();

        parent::tearDown();
    }

    public function test_home_delivery_quotes_an_owned_address_and_adds_shipping_igv(): void
    {
        Setting::setValue(Setting::FREE_SHIPPING_THRESHOLD, '999.00');
        $user = User::factory()->create();
        $address = CustomerAddress::factory()->for($user)->default()->create();
        $this->activeDistrict('11.80');
        $this->withCart($user, '118.00');

        $response = $this->postJson(route('checkout.delivery.quote'), [
            'delivery_method' => DeliveryMethod::HomeDelivery->value,
            'address_id' => $address->id,
        ])
            ->assertOk()
            ->assertJsonPath('delivery.available', true)
            ->assertJsonPath('delivery.method', DeliveryMethod::HomeDelivery->value)
            ->assertJsonPath('delivery.address_id', $address->id)
            ->assertJsonPath('delivery.ubigeo', '150140')
            ->assertJsonPath('delivery.base_fee_cents', 1180)
            ->assertJsonPath('delivery.shipping_fee_cents', 1180)
            ->assertJsonPath('delivery.has_free_shipping', false)
            ->assertJsonPath('delivery.summary.amounts.products_subtotal_cents', 11800)
            ->assertJsonPath('delivery.summary.amounts.shipping_net_value_cents', 1000)
            ->assertJsonPath('delivery.summary.amounts.shipping_tax_cents', 180)
            ->assertJsonPath('delivery.summary.amounts.taxable_value_cents', 11000)
            ->assertJsonPath('delivery.summary.amounts.tax_cents', 1980)
            ->assertJsonPath('delivery.summary.amounts.total_cents', 12980)
            ->assertJsonPath('cart.product_count', 1)
            ->assertJsonPath('cart.total_quantity', 1)
            ->assertJsonPath('cart.items.0.quantity', 1)
            ->assertJsonPath('checkout.product_count', 1)
            ->assertJsonPath('checkout.total_quantity', 1)
            ->assertJsonPath('checkout.items.0.quantity', 1)
            ->assertJsonPath('checkout.amounts.shipping_fee_cents', 0)
            ->assertJsonPath('checkout.amounts.total_cents', 11800);

        $this->assertMatchesRegularExpression(
            '/^[a-f0-9]{64}$/',
            (string) $response->json('delivery.quote_reference'),
        );
        $this->assertNoCheckoutDomainRecords();
    }

    public function test_pickup_quotes_without_an_address_or_shipping_charge(): void
    {
        Setting::setValue(Setting::PICKUP_ADDRESS, 'Av. Javier Prado 1234, San Isidro');
        $user = User::factory()->create();
        $this->withCart($user, '118.00');

        $this->postJson(route('checkout.delivery.quote'), [
            'delivery_method' => DeliveryMethod::Pickup->value,
        ])
            ->assertOk()
            ->assertJsonPath('delivery.method', DeliveryMethod::Pickup->value)
            ->assertJsonPath('delivery.address_id', null)
            ->assertJsonPath('delivery.ubigeo', null)
            ->assertJsonPath('delivery.base_fee_cents', 0)
            ->assertJsonPath('delivery.shipping_fee_cents', 0)
            ->assertJsonPath('delivery.is_pickup', true)
            ->assertJsonPath('delivery.pickup_address', 'Av. Javier Prado 1234, San Isidro')
            ->assertJsonPath('delivery.summary.amounts.shipping_tax_cents', 0)
            ->assertJsonPath('delivery.summary.amounts.tax_cents', 1800)
            ->assertJsonPath('delivery.summary.amounts.total_cents', 11800);

        $this->assertDatabaseCount('customer_addresses', 0);
        $this->assertNoCheckoutDomainRecords();
    }

    public function test_pickup_is_rejected_when_the_store_address_is_disabled(): void
    {
        Setting::setValue(Setting::PICKUP_ADDRESS, '');
        $user = User::factory()->create();
        $this->withCart($user);

        $this->postJson(route('checkout.delivery.quote'), [
            'delivery_method' => DeliveryMethod::Pickup->value,
        ])
            ->assertUnprocessable()
            ->assertJsonPath('delivery.available', false)
            ->assertJsonPath('delivery.method', DeliveryMethod::Pickup->value)
            ->assertJsonPath('delivery.pickup_available', false)
            ->assertJsonPath(
                'errors.delivery_method.0',
                'El recojo en tienda no esta disponible en este momento.',
            );

        $this->assertNoCheckoutDomainRecords();
    }

    public function test_an_inactive_district_returns_a_structured_error_without_deleting_the_address(): void
    {
        $user = User::factory()->create();
        $address = CustomerAddress::factory()->for($user)->default()->create();
        DeliveryDistrict::factory()->inactive()->create([
            'ubigeo' => $address->ubigeo,
            'district' => $address->district,
            'shipping_fee' => '11.80',
        ]);
        $this->withCart($user, '118.00');

        $this->postJson(route('checkout.delivery.quote'), [
            'delivery_method' => DeliveryMethod::HomeDelivery->value,
            'address_id' => $address->id,
        ])
            ->assertUnprocessable()
            ->assertJsonPath('delivery.available', false)
            ->assertJsonPath('delivery.method', DeliveryMethod::HomeDelivery->value)
            ->assertJsonPath('delivery.summary.amounts.products_subtotal_cents', 11800)
            ->assertJsonPath('delivery.summary.amounts.shipping_fee_cents', 0)
            ->assertJsonPath(
                'errors.delivery_method.0',
                'La entrega a domicilio no esta disponible para el distrito seleccionado.',
            );

        $this->assertDatabaseHas('customer_addresses', [
            'id' => $address->id,
            'user_id' => $user->id,
        ]);
        $this->assertNoCheckoutDomainRecords();
    }

    public function test_a_zero_district_fee_is_reported_as_free_without_using_the_threshold(): void
    {
        Setting::setValue(Setting::FREE_SHIPPING_THRESHOLD, '999.00');
        $user = User::factory()->create();
        $address = CustomerAddress::factory()->for($user)->default()->create();
        $this->activeDistrict('0.00');
        $this->withCart($user, '118.00');

        $this->postJson(route('checkout.delivery.quote'), [
            'delivery_method' => DeliveryMethod::HomeDelivery->value,
            'address_id' => $address->id,
        ])
            ->assertOk()
            ->assertJsonPath('delivery.base_fee_cents', 0)
            ->assertJsonPath('delivery.shipping_fee_cents', 0)
            ->assertJsonPath('delivery.has_free_shipping', false)
            ->assertJsonPath('delivery.is_free_by_district', true)
            ->assertJsonPath('delivery.summary.amounts.total_cents', 11800);
    }

    public function test_the_free_shipping_threshold_keeps_the_base_fee_and_removes_the_charge(): void
    {
        Setting::setValue(Setting::FREE_SHIPPING_THRESHOLD, '118.00');
        $user = User::factory()->create();
        $address = CustomerAddress::factory()->for($user)->default()->create();
        $this->activeDistrict('11.80');
        $this->withCart($user, '118.00');

        $this->postJson(route('checkout.delivery.quote'), [
            'delivery_method' => DeliveryMethod::HomeDelivery->value,
            'address_id' => $address->id,
        ])
            ->assertOk()
            ->assertJsonPath('delivery.base_fee_cents', 1180)
            ->assertJsonPath('delivery.shipping_fee_cents', 0)
            ->assertJsonPath('delivery.has_free_shipping', true)
            ->assertJsonPath('delivery.is_free_by_district', false)
            ->assertJsonPath('delivery.summary.amounts.shipping_tax_cents', 0)
            ->assertJsonPath('delivery.summary.amounts.total_cents', 11800);
    }

    public function test_a_new_address_is_previewed_only_from_a_canonical_ubigeo_without_being_created(): void
    {
        Setting::setValue(Setting::FREE_SHIPPING_THRESHOLD, '999.00');
        $user = User::factory()->create();
        $this->activeDistrict('11.80');
        $this->withCart($user, '59.00');

        $this->postJson(route('checkout.delivery.quote'), [
            'delivery_method' => DeliveryMethod::HomeDelivery->value,
            'ubigeo' => '150140',
        ])
            ->assertOk()
            ->assertJsonPath('delivery.address_id', null)
            ->assertJsonPath('delivery.ubigeo', '150140')
            ->assertJsonPath('delivery.district', 'Santiago de Surco')
            ->assertJsonPath('delivery.shipping_fee_cents', 1180);

        $this->assertDatabaseCount('customer_addresses', 0);

        $this->postJson(route('checkout.delivery.quote'), [
            'delivery_method' => DeliveryMethod::HomeDelivery->value,
            'ubigeo' => '999999',
        ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['ubigeo']);
    }

    public function test_quote_rejects_ambiguous_or_incompatible_delivery_identifiers(): void
    {
        Setting::setValue(Setting::PICKUP_ADDRESS, 'Av. Javier Prado 1234, San Isidro');
        $user = User::factory()->create();
        $address = CustomerAddress::factory()->for($user)->default()->create();
        $this->activeDistrict('11.80');
        $this->withCart($user);

        $this->postJson(route('checkout.delivery.quote'), [
            'delivery_method' => DeliveryMethod::HomeDelivery->value,
            'address_id' => $address->id,
            'ubigeo' => '150140',
        ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['address_id']);

        $this->postJson(route('checkout.delivery.quote'), [
            'delivery_method' => DeliveryMethod::Pickup->value,
            'address_id' => $address->id,
        ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['delivery_method']);
    }

    public function test_home_delivery_rejects_an_address_owned_by_another_customer(): void
    {
        $user = User::factory()->create();
        $foreignAddress = CustomerAddress::factory()->default()->create();
        $this->activeDistrict('11.80');
        $this->withCart($user);

        $this->postJson(route('checkout.delivery.quote'), [
            'delivery_method' => DeliveryMethod::HomeDelivery->value,
            'address_id' => $foreignAddress->id,
        ])
            ->assertUnprocessable()
            ->assertJsonStructure(['errors' => ['address_id']]);

        $this->assertNoCheckoutDomainRecords();
    }

    public function test_quote_endpoint_requires_authentication_verification_and_a_real_cart(): void
    {
        Setting::setValue(Setting::PICKUP_ADDRESS, 'Av. Javier Prado 1234, San Isidro');
        $payload = ['delivery_method' => DeliveryMethod::Pickup->value];

        $this->postJson(route('checkout.delivery.quote'), $payload)
            ->assertRedirect(route('login'));

        $this->actingAs(User::factory()->unverified()->create())
            ->postJson(route('checkout.delivery.quote'), $payload)
            ->assertForbidden();

        $this->actingAs(User::factory()->create())
            ->postJson(route('checkout.delivery.quote'), $payload)
            ->assertConflict()
            ->assertJsonPath('errors.cart.0', 'Tu carrito esta vacio.')
            ->assertJsonPath('cart.items', [])
            ->assertJsonPath('cart.product_count', 0)
            ->assertJsonPath('cart.total_quantity', 0)
            ->assertJsonPath('redirect_url', route('shop.cart'));

        $this->assertNoCheckoutDomainRecords();
    }

    public function test_quote_response_synchronizes_reduced_and_removed_cart_items(): void
    {
        Setting::setValue(Setting::PICKUP_ADDRESS, 'Av. Javier Prado 1234, San Isidro');
        $user = User::factory()->create();
        $reduced = $this->withCart($user, '59.00', 4);
        $removed = Product::factory()->create([
            'name' => 'Producto agotado',
            'price' => '20.00',
            'stock' => 5,
        ]);
        app(CartService::class)->add($removed, 3);

        $reduced->update(['stock' => 2]);
        $removed->update(['stock' => 0]);

        $this->postJson(route('checkout.delivery.quote'), [
            'delivery_method' => DeliveryMethod::Pickup->value,
        ])
            ->assertOk()
            ->assertJsonPath('cart.product_count', 1)
            ->assertJsonPath('cart.total_quantity', 2)
            ->assertJsonPath('cart.items.0.product_id', $reduced->id)
            ->assertJsonPath('cart.items.0.quantity', 2)
            ->assertJsonPath('cart.items.0.subtotal_cents', 11800)
            ->assertJsonPath('checkout.product_count', 1)
            ->assertJsonPath('checkout.total_quantity', 2)
            ->assertJsonPath('checkout.items.0.product_id', $reduced->id)
            ->assertJsonPath('checkout.items.0.quantity', 2)
            ->assertJsonPath('checkout.amounts.products_subtotal_cents', 11800)
            ->assertJsonPath('checkout.amounts.total_cents', 11800)
            ->assertJsonPath('delivery.summary.amounts.total_cents', 11800)
            ->assertJsonFragment([
                'warnings' => [
                    "{$reduced->name}: solicitaste 4 unidades, pero solo hay 2 disponibles. Actualizamos tu carrito a 2 unidades.",
                    'Producto agotado: solicitaste 3 unidades, pero el producto ya no tiene stock disponible. Lo retiramos de tu carrito.',
                ],
            ])
            ->assertJsonMissing(['product_id' => $removed->id]);

        $this->assertDatabaseHas('cart_items', [
            'product_id' => $reduced->id,
            'quantity' => 2,
        ]);
        $this->assertDatabaseMissing('cart_items', [
            'product_id' => $removed->id,
        ]);
        $this->assertNoCheckoutDomainRecords();
    }

    public function test_quote_returns_an_empty_synchronized_cart_and_redirect_when_stock_removes_every_item(): void
    {
        Setting::setValue(Setting::PICKUP_ADDRESS, 'Av. Javier Prado 1234, San Isidro');
        $user = User::factory()->create();
        $product = $this->withCart($user, '59.00', 2);
        $product->update(['stock' => 0]);

        $this->postJson(route('checkout.delivery.quote'), [
            'delivery_method' => DeliveryMethod::Pickup->value,
        ])
            ->assertConflict()
            ->assertJsonPath('cart.items', [])
            ->assertJsonPath('cart.product_count', 0)
            ->assertJsonPath('cart.total_quantity', 0)
            ->assertJsonPath('redirect_url', route('shop.cart'));

        $this->assertDatabaseMissing('cart_items', [
            'product_id' => $product->id,
        ]);
        $this->assertNoCheckoutDomainRecords();
    }

    public function test_pickup_quote_returns_current_address_window_and_hold_days(): void
    {
        CarbonImmutable::setTestNow('2026-07-20 10:00:00');
        Setting::setValue(Setting::PICKUP_ADDRESS, 'Direccion anterior');
        Setting::setValue(Setting::PICKUP_PREPARATION_BUSINESS_DAYS_MIN, 1);
        Setting::setValue(Setting::PICKUP_PREPARATION_BUSINESS_DAYS_MAX, 1);
        Setting::setValue(Setting::PICKUP_HOLD_DAYS, 14);
        $user = User::factory()->create();
        $this->withCart($user, '59.00');

        $firstQuote = $this->postJson(route('checkout.delivery.quote'), [
            'delivery_method' => DeliveryMethod::Pickup->value,
        ])
            ->assertOk()
            ->assertJsonPath('delivery.pickup_address', 'Direccion anterior')
            ->assertJsonPath('delivery.estimated_from', '2026-07-21')
            ->assertJsonPath('delivery.estimated_to', '2026-07-21')
            ->assertJsonPath('delivery.estimated_date_label', '21 de julio')
            ->assertJsonPath('delivery.pickup_availability_label', 'el 21 de julio')
            ->assertJsonPath('delivery.pickup_hold_days', 14)
            ->json('delivery.quote_reference');

        Setting::setValue(Setting::PICKUP_ADDRESS, 'Av. Los Eucaliptos 456, San Isidro');
        Setting::setValue(Setting::PICKUP_PREPARATION_BUSINESS_DAYS_MIN, 2);
        Setting::setValue(Setting::PICKUP_PREPARATION_BUSINESS_DAYS_MAX, 4);
        Setting::setValue(Setting::PICKUP_HOLD_DAYS, 21);

        $response = $this->postJson(route('checkout.delivery.quote'), [
            'delivery_method' => DeliveryMethod::Pickup->value,
        ])
            ->assertOk()
            ->assertJsonPath('delivery.pickup_address', 'Av. Los Eucaliptos 456, San Isidro')
            ->assertJsonPath('delivery.estimated_from', '2026-07-22')
            ->assertJsonPath('delivery.estimated_to', '2026-07-24')
            ->assertJsonPath('delivery.estimated_date_label', 'del 22 al 24 de julio')
            ->assertJsonPath('delivery.pickup_availability_label', 'entre el 22 y el 24 de julio')
            ->assertJsonPath(
                'delivery.message',
                'Recojo sin costo. Tu pedido estara disponible para recojo entre el 22 y el 24 de julio. Te avisaremos apenas este listo.',
            )
            ->assertJsonPath('delivery.pickup_hold_days', 21)
            ->assertJsonPath('cart.total_quantity', 1)
            ->assertJsonPath('checkout.total_quantity', 1);

        $this->assertNotSame($firstQuote, $response->json('delivery.quote_reference'));
        $this->assertNoCheckoutDomainRecords();
    }

    public function test_home_quote_uses_the_district_window_and_skips_non_working_dates(): void
    {
        CarbonImmutable::setTestNow('2026-07-20 10:00:00');
        Setting::setValue(Setting::FREE_SHIPPING_THRESHOLD, '999.00');
        $user = User::factory()->create();
        $address = CustomerAddress::factory()->for($user)->default()->create();
        $district = $this->activeDistrict('11.80');
        $district->update([
            'delivery_business_days_min' => 2,
            'delivery_business_days_max' => 3,
        ]);
        NonWorkingDay::factory()->create(['date' => '2026-07-22']);
        $this->withCart($user, '59.00');

        $response = $this->postJson(route('checkout.delivery.quote'), [
            'delivery_method' => DeliveryMethod::HomeDelivery->value,
            'address_id' => $address->id,
        ])
            ->assertOk()
            ->assertJsonPath('delivery.estimated_from', '2026-07-23')
            ->assertJsonPath('delivery.estimated_to', '2026-07-24')
            ->assertJsonPath('delivery.estimated_date_label', 'del 23 al 24 de julio')
            ->assertJsonPath('delivery.delivery_window_label', 'del 23 al 24 de julio')
            ->assertSee('Entrega estimada: del 23 al 24 de julio.');

        $firstReference = $response->json('delivery.quote_reference');
        NonWorkingDay::factory()->create(['date' => '2026-07-23']);

        $secondReference = $this->postJson(route('checkout.delivery.quote'), [
            'delivery_method' => DeliveryMethod::HomeDelivery->value,
            'address_id' => $address->id,
        ])->assertOk()->json('delivery.quote_reference');

        $this->assertNotSame($firstReference, $secondReference);
        $this->assertNoCheckoutDomainRecords();
    }

    public function test_quote_ignores_manipulated_amounts_and_recalculates_from_the_real_cart(): void
    {
        Setting::setValue(Setting::FREE_SHIPPING_THRESHOLD, '999.00');
        $user = User::factory()->create();
        $address = CustomerAddress::factory()->for($user)->default()->create();
        $this->activeDistrict('11.80');
        $product = $this->withCart($user, '118.00', 2);

        $this->postJson(route('checkout.delivery.quote'), [
            'delivery_method' => DeliveryMethod::HomeDelivery->value,
            'address_id' => $address->id,
            'products_subtotal_cents' => 1,
            'discount_cents' => 999999,
            'shipping_fee_cents' => 1,
            'shipping_tax_cents' => 0,
            'tax_cents' => 0,
            'total_cents' => 1,
            'items' => [],
        ])
            ->assertOk()
            ->assertJsonPath('delivery.summary.amounts.products_subtotal_cents', 23600)
            ->assertJsonPath('delivery.summary.amounts.discount_cents', 0)
            ->assertJsonPath('delivery.summary.amounts.shipping_fee_cents', 1180)
            ->assertJsonPath('delivery.summary.amounts.shipping_tax_cents', 180)
            ->assertJsonPath('delivery.summary.amounts.taxable_value_cents', 21000)
            ->assertJsonPath('delivery.summary.amounts.tax_cents', 3780)
            ->assertJsonPath('delivery.summary.amounts.total_cents', 24780);

        $this->assertDatabaseHas('cart_items', [
            'product_id' => $product->id,
            'quantity' => 2,
        ]);
        $this->assertSame(20, $product->fresh()->stock);
        $this->assertNoCheckoutDomainRecords();
    }

    private function activeDistrict(string $shippingFee): DeliveryDistrict
    {
        return DeliveryDistrict::factory()->create([
            'ubigeo' => '150140',
            'province_code' => '1501',
            'department' => 'Lima',
            'province' => 'Lima',
            'district' => 'Santiago de Surco',
            'shipping_fee' => $shippingFee,
            'is_active' => true,
        ]);
    }

    private function withCart(
        User $user,
        string $price = '59.00',
        int $quantity = 1,
    ): Product {
        $product = Product::factory()->create([
            'price' => $price,
            'stock' => 20,
        ]);

        $this->actingAs($user);
        app(CartService::class)->add($product, $quantity);

        return $product;
    }

    private function assertNoCheckoutDomainRecords(): void
    {
        $this->assertDatabaseCount('order_sequences', 0);
        $this->assertDatabaseCount('orders', 0);
        $this->assertDatabaseCount('order_items', 0);
        $this->assertDatabaseCount('order_status_histories', 0);
        $this->assertDatabaseCount('stock_reservations', 0);
        $this->assertDatabaseCount('inventory_movements', 0);
    }
}
