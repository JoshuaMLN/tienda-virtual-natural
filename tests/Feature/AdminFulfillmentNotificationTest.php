<?php

namespace Tests\Feature;

use App\Enums\AdminFulfillmentFilter;
use App\Enums\DeliveryMethod;
use App\Enums\DeliveryStatus;
use App\Enums\DeliveryTrackingStatus;
use App\Models\Order;
use App\Support\Notifications\AdminNotificationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class AdminFulfillmentNotificationTest extends TestCase
{
    use RefreshDatabase;

    protected function tearDown(): void
    {
        Carbon::setTestNow();

        parent::tearDown();
    }

    public function test_it_does_not_generate_fulfillment_alerts_without_matching_orders(): void
    {
        Order::factory()->create();

        $notifications = app(AdminNotificationService::class)->getAll();

        $this->assertSame([], $notifications);
    }

    public function test_it_groups_the_four_fulfillment_alerts_with_their_filters(): void
    {
        Carbon::setTestNow('2026-07-31 10:00:00');

        Order::factory()->pickup()->create([
            'delivery_status' => DeliveryStatus::ReadyForPickup,
            'delivery_tracking_status' => DeliveryTrackingStatus::Active,
            'pickup_deadline_at' => now()->subMinute(),
        ]);

        Order::factory()->pickup()->create([
            'delivery_status' => DeliveryStatus::ReadyForPickup,
            'delivery_tracking_status' => DeliveryTrackingStatus::Active,
            'pickup_deadline_at' => now()->addHours(48),
        ]);

        Order::factory()->create([
            'delivery_tracking_status' => DeliveryTrackingStatus::AwaitingReshipmentPayment,
        ]);

        Order::factory()->create([
            'delivery_method' => DeliveryMethod::HomeDelivery,
            'delivery_tracking_status' => DeliveryTrackingStatus::ManualFollowUp,
        ]);

        $notifications = app(AdminNotificationService::class)->getAll();

        $this->assertCount(4, $notifications);

        $this->assertSame('Recojos vencidos', $notifications[0]->title);
        $this->assertSame('1 pedido supero su plazo de recojo', $notifications[0]->message);
        $this->assertSame(
            route('admin.orders.index', ['seguimiento' => AdminFulfillmentFilter::PickupOverdue->value]),
            $notifications[0]->url,
        );

        $this->assertSame('Recojos por vencer', $notifications[1]->title);
        $this->assertSame('1 pedido vence dentro de las proximas 48 horas', $notifications[1]->message);
        $this->assertSame(
            route('admin.orders.index', ['seguimiento' => AdminFulfillmentFilter::PickupDueSoon->value]),
            $notifications[1]->url,
        );

        $this->assertSame('Reenvios pendientes', $notifications[2]->title);
        $this->assertSame('1 pedido espera el pago de un nuevo envio', $notifications[2]->message);
        $this->assertSame(
            route('admin.orders.index', ['seguimiento' => AdminFulfillmentFilter::ReshipmentPending->value]),
            $notifications[2]->url,
        );

        $this->assertSame('Seguimiento manual', $notifications[3]->title);
        $this->assertSame('1 pedido requiere seguimiento manual', $notifications[3]->message);
        $this->assertSame(
            route('admin.orders.index', ['seguimiento' => AdminFulfillmentFilter::ManualFollowUp->value]),
            $notifications[3]->url,
        );
    }

    public function test_overdue_pickup_is_not_duplicated_as_manual_follow_up(): void
    {
        Carbon::setTestNow('2026-07-31 10:00:00');

        Order::factory()->pickup()->create([
            'delivery_status' => DeliveryStatus::ReadyForPickup,
            'delivery_tracking_status' => DeliveryTrackingStatus::ManualFollowUp,
            'pickup_deadline_at' => now(),
        ]);

        $notifications = app(AdminNotificationService::class)->getAll();

        $this->assertCount(1, $notifications);
        $this->assertSame('Recojos vencidos', $notifications[0]->title);
        $this->assertStringContainsString(
            'seguimiento='.AdminFulfillmentFilter::PickupOverdue->value,
            $notifications[0]->url,
        );
    }
}
