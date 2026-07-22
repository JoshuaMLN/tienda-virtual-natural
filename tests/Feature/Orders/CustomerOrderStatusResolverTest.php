<?php

namespace Tests\Feature\Orders;

use App\Enums\CustomerOrderFilter;
use App\Enums\CustomerOrderStatus;
use App\Enums\DeliveryMethod;
use App\Enums\DeliveryStatus;
use App\Enums\OrderStatus;
use App\Enums\PaymentStatus;
use App\Models\Order;
use App\Models\User;
use App\Support\Orders\CustomerOrderStatusResolver;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CustomerOrderStatusResolverTest extends TestCase
{
    use RefreshDatabase;

    private CustomerOrderStatusResolver $resolver;

    private User $customer;

    protected function setUp(): void
    {
        parent::setUp();

        CarbonImmutable::setTestNow('2026-07-22 10:00:00');
        $this->resolver = app(CustomerOrderStatusResolver::class);
        $this->customer = User::factory()->create();
    }

    protected function tearDown(): void
    {
        CarbonImmutable::setTestNow();

        parent::tearDown();
    }

    public function test_it_derives_every_customer_facing_order_status(): void
    {
        $orders = [
            CustomerOrderStatus::PendingPayment->value => $this->order(1),
            CustomerOrderStatus::PaymentFailed->value => $this->order(2, [
                'payment_status' => PaymentStatus::Failed,
                'reservation_expires_at' => now()->addMinute(),
            ]),
            CustomerOrderStatus::Preparing->value => $this->order(3, [
                'order_status' => OrderStatus::Processing,
                'payment_status' => PaymentStatus::Paid,
            ]),
            CustomerOrderStatus::InTransit->value => $this->order(4, [
                'order_status' => OrderStatus::Processing,
                'payment_status' => PaymentStatus::Paid,
                'delivery_status' => DeliveryStatus::Shipped,
            ]),
            CustomerOrderStatus::ReadyForPickup->value => $this->order(5, [
                'order_status' => OrderStatus::Processing,
                'payment_status' => PaymentStatus::Paid,
                'delivery_status' => DeliveryStatus::ReadyForPickup,
                'delivery_method' => DeliveryMethod::Pickup,
            ]),
            CustomerOrderStatus::Delivered->value => $this->order(6, [
                'order_status' => OrderStatus::Completed,
                'payment_status' => PaymentStatus::Paid,
                'delivery_status' => DeliveryStatus::Delivered,
            ]),
            CustomerOrderStatus::PickedUp->value => $this->order(7, [
                'order_status' => OrderStatus::Completed,
                'payment_status' => PaymentStatus::Paid,
                'delivery_status' => DeliveryStatus::PickedUp,
                'delivery_method' => DeliveryMethod::Pickup,
            ]),
            CustomerOrderStatus::Cancelled->value => $this->order(8, [
                'order_status' => OrderStatus::Cancelled,
            ]),
            CustomerOrderStatus::Expired->value => $this->order(9, [
                'order_status' => OrderStatus::Expired,
                'payment_status' => PaymentStatus::Expired,
            ]),
            CustomerOrderStatus::Refunded->value => $this->order(10, [
                'order_status' => OrderStatus::Cancelled,
                'payment_status' => PaymentStatus::Refunded,
            ]),
        ];

        foreach ($orders as $expected => $order) {
            $this->assertSame($expected, $this->resolver->resolve($order)->value);
        }
    }

    public function test_failed_payment_stays_failed_while_its_reservation_is_valid(): void
    {
        $order = $this->order(11, [
            'payment_status' => PaymentStatus::Failed,
            'reservation_expires_at' => now()->addSecond(),
        ]);

        $this->assertSame(CustomerOrderStatus::PaymentFailed, $this->resolver->resolve($order));
    }

    public function test_due_reservation_is_presented_as_expired_before_the_scheduler_updates_the_order(): void
    {
        $order = $this->order(12, [
            'payment_status' => PaymentStatus::Failed,
            'reservation_expires_at' => now(),
        ]);

        $this->assertSame(OrderStatus::PendingPayment, $order->order_status);
        $this->assertSame(PaymentStatus::Failed, $order->payment_status);
        $this->assertSame(CustomerOrderStatus::Expired, $this->resolver->resolve($order));
    }

    public function test_each_filter_group_returns_only_its_commercial_states(): void
    {
        $pending = $this->order(20);
        $failed = $this->order(21, [
            'payment_status' => PaymentStatus::Failed,
            'reservation_expires_at' => now()->addMinute(),
        ]);
        $preparing = $this->order(22, [
            'order_status' => OrderStatus::Processing,
            'payment_status' => PaymentStatus::Paid,
            'delivery_status' => DeliveryStatus::Preparing,
        ]);
        $inTransit = $this->order(23, [
            'order_status' => OrderStatus::Processing,
            'payment_status' => PaymentStatus::Paid,
            'delivery_status' => DeliveryStatus::Shipped,
        ]);
        $readyForPickup = $this->order(24, [
            'order_status' => OrderStatus::Processing,
            'payment_status' => PaymentStatus::Paid,
            'delivery_status' => DeliveryStatus::ReadyForPickup,
            'delivery_method' => DeliveryMethod::Pickup,
        ]);
        $delivered = $this->order(25, [
            'order_status' => OrderStatus::Completed,
            'payment_status' => PaymentStatus::Paid,
            'delivery_status' => DeliveryStatus::Delivered,
        ]);
        $pickedUp = $this->order(26, [
            'order_status' => OrderStatus::Completed,
            'payment_status' => PaymentStatus::Paid,
            'delivery_status' => DeliveryStatus::PickedUp,
            'delivery_method' => DeliveryMethod::Pickup,
        ]);
        $cancelled = $this->order(27, [
            'order_status' => OrderStatus::Cancelled,
        ]);
        $refunded = $this->order(28, [
            'payment_status' => PaymentStatus::Refunded,
        ]);
        $expiredByDate = $this->order(29, [
            'payment_status' => PaymentStatus::Failed,
            'reservation_expires_at' => now()->subSecond(),
        ]);

        $expectedByFilter = [
            CustomerOrderFilter::All->value => [
                $pending->code,
                $failed->code,
                $preparing->code,
                $inTransit->code,
                $readyForPickup->code,
                $delivered->code,
                $pickedUp->code,
                $cancelled->code,
                $refunded->code,
                $expiredByDate->code,
            ],
            CustomerOrderFilter::Pending->value => [$pending->code, $failed->code],
            CustomerOrderFilter::Preparing->value => [$preparing->code],
            CustomerOrderFilter::Fulfillment->value => [$inTransit->code, $readyForPickup->code],
            CustomerOrderFilter::Completed->value => [$delivered->code, $pickedUp->code],
            CustomerOrderFilter::Closed->value => [$cancelled->code, $refunded->code, $expiredByDate->code],
        ];

        foreach ($expectedByFilter as $filter => $expectedCodes) {
            $query = $this->customer->orders()->getQuery();
            $this->resolver->constrain($query, CustomerOrderFilter::from($filter));

            $this->assertEqualsCanonicalizing($expectedCodes, $query->pluck('code')->all(), "Filtro {$filter}");
        }
    }

    /** @param array<string, mixed> $attributes */
    private function order(int $sequence, array $attributes = []): Order
    {
        return Order::factory()->for($this->customer)->create(array_merge([
            'code' => sprintf('PED-2026-%06d', $sequence),
            'sequence_year' => 2026,
            'sequence_number' => $sequence,
            'order_status' => OrderStatus::PendingPayment,
            'payment_status' => PaymentStatus::Pending,
            'delivery_status' => DeliveryStatus::Pending,
            'reservation_expires_at' => null,
        ], $attributes));
    }
}
