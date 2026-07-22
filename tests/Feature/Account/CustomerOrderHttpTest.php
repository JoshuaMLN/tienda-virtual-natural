<?php

namespace Tests\Feature\Account;

use App\Enums\DeliveryMethod;
use App\Enums\DeliveryStatus;
use App\Enums\OrderStatus;
use App\Enums\PaymentStatus;
use App\Models\Order;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Pagination\LengthAwarePaginator;
use Tests\TestCase;

class CustomerOrderHttpTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config()->set('app.locale', 'es');
        config()->set('app.timezone', 'America/Lima');
        CarbonImmutable::setTestNow('2026-07-22 10:00:00');
    }

    protected function tearDown(): void
    {
        CarbonImmutable::setTestNow();

        parent::tearDown();
    }

    public function test_guest_must_authenticate_to_access_order_list_and_detail(): void
    {
        $this->get(route('account.orders'))
            ->assertRedirect(route('login'));

        $this->get(route('account.orders.show', 'PED-2026-000001'))
            ->assertRedirect(route('login'));
    }

    public function test_order_list_is_isolated_to_the_authenticated_customer(): void
    {
        $customer = User::factory()->create();
        $otherCustomer = User::factory()->create();
        $ownOrder = $this->order($customer, 1);
        $foreignOrder = $this->order($otherCustomer, 2);

        $this->actingAs($customer)
            ->get(route('account.orders'))
            ->assertOk()
            ->assertSee($ownOrder->code)
            ->assertDontSee($foreignOrder->code);
    }

    public function test_orders_are_sorted_by_creation_date_and_id_descending(): void
    {
        $customer = User::factory()->create();
        $oldest = $this->order($customer, 10, ['created_at' => now()->subDays(2)]);
        $sameDateFirst = $this->order($customer, 11, ['created_at' => now()->subDay()]);
        $sameDateLast = $this->order($customer, 12, ['created_at' => now()->subDay()]);

        $response = $this->actingAs($customer)->get(route('account.orders'));

        $response->assertOk();
        $this->assertSame(
            [$sameDateLast->code, $sameDateFirst->code, $oldest->code],
            $this->codesFrom($response->viewData('orders')),
        );
    }

    public function test_order_list_paginates_ten_records_and_preserves_the_query_string(): void
    {
        $customer = User::factory()->create();

        foreach (range(1, 11) as $sequence) {
            $this->order($customer, $sequence, [
                'created_at' => now()->subMinutes(11 - $sequence),
            ]);
        }

        $firstPage = $this->actingAs($customer)
            ->get(route('account.orders', ['estado' => 'pending']));

        $firstPage->assertOk();
        $paginator = $firstPage->viewData('orders');
        $this->assertInstanceOf(LengthAwarePaginator::class, $paginator);
        $this->assertSame(10, $paginator->perPage());
        $this->assertSame(11, $paginator->total());
        $this->assertCount(10, $paginator->items());
        $firstPage->assertSee('estado=pending&amp;page=2', false);

        $secondPage = $this->get(route('account.orders', [
            'estado' => 'pending',
            'page' => 2,
        ]));

        $secondPage->assertOk();
        $this->assertCount(1, $secondPage->viewData('orders')->items());
    }

    public function test_search_trims_and_normalizes_a_lowercase_order_code(): void
    {
        $customer = User::factory()->create();
        $matching = $this->order($customer, 123);
        $other = $this->order($customer, 999);

        $response = $this->actingAs($customer)->get(route('account.orders', [
            'q' => '  ped-2026-000123  ',
        ]));

        $response->assertOk()
            ->assertSee($matching->code)
            ->assertDontSee($other->code)
            ->assertSee('value="PED-2026-000123"', false);
        $this->assertSame([$matching->code], $this->codesFrom($response->viewData('orders')));
    }

    public function test_every_filter_group_is_available_and_returns_its_expected_orders(): void
    {
        $customer = User::factory()->create();
        $pending = $this->order($customer, 30);
        $preparing = $this->order($customer, 31, [
            'order_status' => OrderStatus::Processing,
            'payment_status' => PaymentStatus::Paid,
            'delivery_status' => DeliveryStatus::Preparing,
        ]);
        $fulfillment = $this->order($customer, 32, [
            'order_status' => OrderStatus::Processing,
            'payment_status' => PaymentStatus::Paid,
            'delivery_status' => DeliveryStatus::Shipped,
        ]);
        $completed = $this->order($customer, 33, [
            'order_status' => OrderStatus::Completed,
            'payment_status' => PaymentStatus::Paid,
            'delivery_status' => DeliveryStatus::Delivered,
        ]);
        $closed = $this->order($customer, 34, [
            'order_status' => OrderStatus::Cancelled,
        ]);

        $expectedByFilter = [
            'all' => [$closed->code, $completed->code, $fulfillment->code, $preparing->code, $pending->code],
            'pending' => [$pending->code],
            'preparing' => [$preparing->code],
            'fulfillment' => [$fulfillment->code],
            'completed' => [$completed->code],
            'closed' => [$closed->code],
        ];

        foreach ($expectedByFilter as $filter => $expectedCodes) {
            $response = $this->actingAs($customer)->get(route('account.orders', ['estado' => $filter]));

            $response->assertOk();
            $this->assertSame($expectedCodes, $this->codesFrom($response->viewData('orders')), "Filtro {$filter}");
        }
    }

    public function test_list_renders_desktop_table_and_mobile_cards_with_real_order_data(): void
    {
        $customer = User::factory()->create();
        $order = $this->order($customer, 40, [
            'total_cents' => 17_660,
            'delivery_method' => DeliveryMethod::Pickup,
        ]);

        $this->actingAs($customer)
            ->get(route('account.orders'))
            ->assertOk()
            ->assertSee('customer-order-table-wrap d-none d-md-block', false)
            ->assertSee('customer-order-mobile-list d-md-none', false)
            ->assertSee("pedido-{$order->code}", false)
            ->assertSee("pedido-mobile-{$order->code}", false)
            ->assertSee('22/07/2026')
            ->assertSee('10:00 a. m.')
            ->assertSee('Recojo en tienda')
            ->assertSee('S/ 176.60');
    }

    public function test_customer_can_open_own_basic_order_detail_but_foreign_order_returns_404(): void
    {
        $customer = User::factory()->create();
        $otherCustomer = User::factory()->create();
        $ownOrder = $this->order($customer, 50, [
            'total_cents' => 25_900,
            'delivery_method' => DeliveryMethod::HomeDelivery,
        ]);
        $foreignOrder = $this->order($otherCustomer, 51);

        $this->actingAs($customer)
            ->get(route('account.orders.show', $ownOrder->code))
            ->assertOk()
            ->assertSee($ownOrder->code)
            ->assertSee('Resumen de compra')
            ->assertSee('Entrega a domicilio')
            ->assertSee('S/ 259.00');

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
            'reservation_expires_at' => now()->addMinutes(15),
        ], $attributes));
    }

    /** @return list<string> */
    private function codesFrom(LengthAwarePaginator $orders): array
    {
        return array_map(
            fn (array $item): string => $item['order']->code,
            $orders->items(),
        );
    }
}
