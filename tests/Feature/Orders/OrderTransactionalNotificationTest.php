<?php

namespace Tests\Feature\Orders;

use App\Enums\DeliveryStatus;
use App\Enums\OrderHistoryDomain;
use App\Enums\OrderNotificationStatus;
use App\Enums\OrderNotificationType;
use App\Enums\OrderStatus;
use App\Enums\PaymentStatus;
use App\Jobs\SendOrderTransactionalEmail;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\OrderNotificationDelivery;
use App\Models\OrderStatusHistory;
use App\Models\User;
use App\Notifications\OrderTransactionalNotification;
use App\Support\Orders\CustomerOrderDateFormatter;
use App\Support\Orders\Notifications\OrderEmailThumbnail;
use App\Support\Orders\Notifications\OrderEmailThumbnailService;
use App\Support\Orders\Notifications\OrderNotificationDeliveryService;
use App\Support\Orders\Notifications\OrderTransactionalEmailPresenter;
use App\Support\Orders\OrderCancellationDetailsResolver;
use Carbon\CarbonImmutable;
use Illuminate\Contracts\Notifications\Dispatcher;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldBeUniqueUntilProcessing;
use Illuminate\Contracts\Queue\ShouldQueueAfterCommit;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Mail\MailManager;
use Illuminate\Mail\Transport\ArrayTransport;
use Illuminate\Notifications\AnonymousNotifiable;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use LogicException;
use Mockery;
use RuntimeException;
use Symfony\Component\Mime\Email;
use Tests\TestCase;

class OrderTransactionalNotificationTest extends TestCase
{
    use RefreshDatabase;

    protected function tearDown(): void
    {
        CarbonImmutable::setTestNow();

        parent::tearDown();
    }

    public function test_recipients_are_normalized_deduplicated_and_frozen_per_event(): void
    {
        Queue::fake();
        $user = User::factory()->create([
            'name' => 'Maria Nueva',
            'email' => 'actual@example.test',
        ]);
        $order = Order::factory()->for($user)->create([
            'customer_name' => 'Maria Original',
            'customer_email' => 'VENTAS@EXAMPLE.TEST',
        ]);
        $service = app(OrderNotificationDeliveryService::class);

        $first = $service->record($order, OrderNotificationType::Created);

        $this->assertCount(2, $first);
        $this->assertEqualsCanonicalizing(
            ['actual@example.test', 'ventas@example.test'],
            $first->pluck('recipient_email')->all(),
        );
        Queue::assertPushed(SendOrderTransactionalEmail::class, 2);

        $user->update(['email' => 'nuevo@example.test']);
        $user->forceFill(['email_verified_at' => now()])->save();
        $replayed = $service->record($order, OrderNotificationType::Created);

        $this->assertCount(2, $replayed);
        $this->assertDatabaseCount('order_notification_deliveries', 2);
        $this->assertDatabaseMissing('order_notification_deliveries', [
            'recipient_email' => 'nuevo@example.test',
        ]);
        Queue::assertPushed(SendOrderTransactionalEmail::class, 2);
    }

    public function test_unverified_or_deleted_account_only_uses_order_snapshot(): void
    {
        Queue::fake();
        $unverified = User::factory()->unverified()->create([
            'email' => 'sin-verificar@example.test',
        ]);
        $unverifiedOrder = Order::factory()->for($unverified)->create([
            'customer_email' => 'compra@example.test',
        ]);
        $deleted = User::factory()->create(['email' => 'cuenta@example.test']);
        $deletedOrder = Order::factory()->for($deleted)->create([
            'customer_email' => 'historico@example.test',
        ]);
        $deleted->delete();
        $service = app(OrderNotificationDeliveryService::class);

        $service->record($unverifiedOrder, OrderNotificationType::Cancelled);
        $service->record($deletedOrder->refresh(), OrderNotificationType::Expired);

        $this->assertDatabaseHas('order_notification_deliveries', [
            'order_id' => $unverifiedOrder->id,
            'recipient_email' => 'compra@example.test',
        ]);
        $this->assertDatabaseMissing('order_notification_deliveries', [
            'order_id' => $unverifiedOrder->id,
            'recipient_email' => 'sin-verificar@example.test',
        ]);
        $this->assertDatabaseHas('order_notification_deliveries', [
            'order_id' => $deletedOrder->id,
            'recipient_email' => 'historico@example.test',
        ]);
        $this->assertSame(1, $unverifiedOrder->notificationDeliveries()->count());
        $this->assertSame(1, $deletedOrder->notificationDeliveries()->count());
    }

    public function test_equivalent_snapshot_and_current_addresses_create_one_normalized_delivery(): void
    {
        Queue::fake();
        $user = User::factory()->create(['email' => 'cliente@example.test']);
        $order = Order::factory()->for($user)->create([
            'customer_email' => '  CLIENTE@EXAMPLE.TEST  ',
        ]);

        app(OrderNotificationDeliveryService::class)->record(
            $order,
            OrderNotificationType::Created,
        );

        $this->assertDatabaseCount('order_notification_deliveries', 1);
        $this->assertDatabaseHas('order_notification_deliveries', [
            'recipient_email' => 'cliente@example.test',
        ]);
        Queue::assertPushed(SendOrderTransactionalEmail::class, 1);
    }

    public function test_pickup_reminders_are_scheduled_once_and_skip_dates_that_already_passed(): void
    {
        Queue::fake();
        CarbonImmutable::setTestNow('2026-08-01 10:00:00');
        $service = app(OrderNotificationDeliveryService::class);
        $user = User::factory()->create(['email' => 'recojo@example.test']);
        $order = Order::factory()->for($user)->readyForPickup()->create([
            'customer_email' => 'recojo@example.test',
            'pickup_ready_at' => now(),
            'pickup_deadline_at' => now()->addDays(14),
        ]);

        $service->record($order, OrderNotificationType::PickupReady);
        $service->schedulePickupReminders($order);
        $this->assertSame(1, $order->notificationDeliveries()->count());

        CarbonImmutable::setTestNow('2026-08-08 10:00:00');
        $service->schedulePickupReminders($order);

        CarbonImmutable::setTestNow('2026-08-13 10:00:00');
        $service->schedulePickupReminders($order);

        CarbonImmutable::setTestNow('2026-08-15 10:00:00');
        $service->schedulePickupReminders($order);

        $this->assertEqualsCanonicalizing([
            OrderNotificationType::PickupReady->value,
            OrderNotificationType::PickupMidpointReminder->value,
            OrderNotificationType::Pickup48HoursReminder->value,
            OrderNotificationType::PickupDeadlineReminder->value,
        ], $order->notificationDeliveries()
            ->get()
            ->map(fn (OrderNotificationDelivery $delivery): string => $delivery->type->value)
            ->all());
        $this->assertSame(4, $order->notificationDeliveries()->count());
        Queue::assertPushed(SendOrderTransactionalEmail::class, 4);

        $shortHold = Order::factory()->for($user)->readyForPickup()->create([
            'customer_email' => 'recojo@example.test',
            'pickup_ready_at' => now(),
            'pickup_deadline_at' => now()->addDay(),
        ]);
        CarbonImmutable::setTestNow(now()->addHours(12));
        $service->schedulePickupReminders($shortHold);

        CarbonImmutable::setTestNow(now()->addHours(12));
        $service->schedulePickupReminders($shortHold);

        $this->assertEqualsCanonicalizing([
            OrderNotificationType::PickupMidpointReminder->value,
            OrderNotificationType::PickupDeadlineReminder->value,
        ], $shortHold->notificationDeliveries()
            ->get()
            ->map(fn (OrderNotificationDelivery $delivery): string => $delivery->type->value)
            ->all());
    }

    public function test_pickup_completion_supersedes_queued_pickup_reminders_without_touching_sent_history(): void
    {
        Queue::fake();
        Notification::fake();
        $order = Order::factory()->readyForPickup()->create();
        $queued = OrderNotificationDelivery::factory()->for($order)->create([
            'type' => OrderNotificationType::PickupMidpointReminder,
        ]);
        $sent = OrderNotificationDelivery::factory()->for($order)->sent()->create([
            'type' => OrderNotificationType::PickupReady,
            'recipient_email' => 'ready@example.test',
        ]);

        app(OrderNotificationDeliveryService::class)->cancelPendingPickupReminders($order);

        $this->assertSame(OrderNotificationStatus::Superseded, $queued->refresh()->status);
        $this->assertSame('El pedido fue recogido antes de enviar este recordatorio.', $queued->superseded_reason);
        $this->assertSame(OrderNotificationStatus::Sent, $sent->refresh()->status);

        (new SendOrderTransactionalEmail($queued->id))
            ->handle(app(OrderNotificationDeliveryService::class));

        $this->assertSame(0, $queued->refresh()->attempts);
        Notification::assertNothingSent();
    }

    public function test_scheduled_reconciliation_creates_due_pickup_reminders_idempotently(): void
    {
        Queue::fake();
        CarbonImmutable::setTestNow('2026-08-01 10:00:00');
        $user = User::factory()->create(['email' => 'scheduler@example.test']);
        $order = Order::factory()->for($user)->readyForPickup()->create([
            'customer_email' => 'scheduler@example.test',
            'pickup_ready_at' => now()->subDays(4),
            'pickup_deadline_at' => now(),
        ]);

        $this->assertSame(0, Artisan::call('orders:reconcile-notifications'));
        $this->assertStringContainsString('Recordatorios de recojo reconciliados: 1', Artisan::output());
        $this->assertSame(1, $order->notificationDeliveries()->count());

        $this->assertSame(0, Artisan::call('orders:reconcile-notifications'));
        $this->assertSame(1, $order->notificationDeliveries()->count());
        Queue::assertPushed(SendOrderTransactionalEmail::class, 1);
    }

    public function test_terminal_event_supersedes_a_queued_creation_before_sending(): void
    {
        Queue::fake();
        Notification::fake();
        $user = User::factory()->create(['email' => 'cliente@example.test']);
        $order = Order::factory()->for($user)->create([
            'customer_email' => 'cliente@example.test',
        ]);
        $service = app(OrderNotificationDeliveryService::class);
        $created = $service->record($order, OrderNotificationType::Created)->sole();

        $service->record($order, OrderNotificationType::Cancelled);

        $created->refresh();
        $this->assertSame(OrderNotificationStatus::Superseded, $created->status);
        $this->assertNotNull($created->superseded_at);
        $this->assertSame(
            'El pedido fue cancelado antes de enviar esta comunicacion.',
            $created->superseded_reason,
        );
        $this->assertNull($created->last_error);

        (new SendOrderTransactionalEmail($created->id))->handle($service);

        $this->assertSame(0, $created->refresh()->attempts);
        Notification::assertNothingSent();
    }

    public function test_sent_creation_is_preserved_when_a_terminal_event_is_recorded(): void
    {
        Queue::fake();
        $order = Order::factory()->create();
        $created = OrderNotificationDelivery::factory()
            ->for($order)
            ->sent()
            ->create(['type' => OrderNotificationType::Created]);

        app(OrderNotificationDeliveryService::class)->record(
            $order,
            OrderNotificationType::Cancelled,
        );

        $this->assertSame(OrderNotificationStatus::Sent, $created->refresh()->status);
        $this->assertNull($created->superseded_at);
        $this->assertNull($created->superseded_reason);
    }

    public function test_expiration_supersedes_a_queued_creation_with_its_own_reason(): void
    {
        Queue::fake();
        $order = Order::factory()->create();
        $created = OrderNotificationDelivery::factory()
            ->for($order)
            ->create(['type' => OrderNotificationType::Created]);

        app(OrderNotificationDeliveryService::class)->record(
            $order,
            OrderNotificationType::Expired,
        );

        $this->assertSame(OrderNotificationStatus::Superseded, $created->refresh()->status);
        $this->assertSame(
            'La reserva vencio antes de enviar esta comunicacion.',
            $created->superseded_reason,
        );
    }

    public function test_cancellation_waits_for_a_sending_creation_then_supersedes_its_retry(): void
    {
        Queue::fake();
        Notification::fake();
        $order = Order::factory()->create();
        $created = OrderNotificationDelivery::factory()->for($order)->create([
            'type' => OrderNotificationType::Created,
            'status' => OrderNotificationStatus::Sending,
            'attempts' => 1,
            'last_attempt_at' => now(),
        ]);
        $cancelled = OrderNotificationDelivery::factory()->for($order)->create([
            'type' => OrderNotificationType::Cancelled,
            'recipient_email' => 'cancelacion@example.test',
        ]);
        $service = app(OrderNotificationDeliveryService::class);
        $job = new SendOrderTransactionalEmail($cancelled->id);

        $job->handle($service);

        $this->assertSame(OrderNotificationStatus::Queued, $cancelled->refresh()->status);
        $this->assertSame(0, $cancelled->attempts);
        Queue::assertPushed(
            SendOrderTransactionalEmail::class,
            fn (SendOrderTransactionalEmail $redispatched): bool => $redispatched->deliveryId === $cancelled->id
                && $redispatched->delay !== null,
        );
        Notification::assertNothingSent();

        $service->markRetryableFailure($created->id, new RuntimeException('SMTP temporal'));
        $job->handle($service);

        $this->assertSame(OrderNotificationStatus::Superseded, $created->refresh()->status);
        $this->assertSame(OrderNotificationStatus::Sent, $cancelled->refresh()->status);
        $this->assertSame(1, $cancelled->attempts);
        Notification::assertSentOnDemand(OrderTransactionalNotification::class);
    }

    public function test_stale_sending_creation_does_not_block_cancellation_indefinitely(): void
    {
        Queue::fake();
        Notification::fake();
        $order = Order::factory()->create();
        $created = OrderNotificationDelivery::factory()->for($order)->create([
            'type' => OrderNotificationType::Created,
            'status' => OrderNotificationStatus::Sending,
            'attempts' => 1,
            'last_attempt_at' => now()->subMinutes(3),
        ]);
        $cancelled = OrderNotificationDelivery::factory()->for($order)->create([
            'type' => OrderNotificationType::Cancelled,
        ]);
        $service = app(OrderNotificationDeliveryService::class);

        (new SendOrderTransactionalEmail($cancelled->id))->handle($service);

        $this->assertSame(OrderNotificationStatus::Superseded, $created->refresh()->status);
        $this->assertSame(OrderNotificationStatus::Sent, $cancelled->refresh()->status);
        Queue::assertNothingPushed();
        Notification::assertSentOnDemand(OrderTransactionalNotification::class);
    }

    public function test_database_guarantees_uniqueness_and_delivery_identity_is_immutable(): void
    {
        $delivery = OrderNotificationDelivery::factory()->create([
            'type' => OrderNotificationType::Created,
            'recipient_email' => 'cliente@example.test',
        ]);

        try {
            OrderNotificationDelivery::factory()->create([
                'order_id' => $delivery->order_id,
                'type' => OrderNotificationType::Created,
                'recipient_email' => 'CLIENTE@EXAMPLE.TEST',
            ]);
            $this->fail('La base de datos permitio una entrega duplicada.');
        } catch (QueryException) {
            $this->assertDatabaseCount('order_notification_deliveries', 1);
        }

        $this->expectException(LogicException::class);
        $delivery->update(['recipient_email' => 'otro@example.test']);
    }

    public function test_delivery_history_cannot_be_deleted(): void
    {
        $delivery = OrderNotificationDelivery::factory()->create();

        $this->expectException(LogicException::class);
        $delivery->delete();
    }

    public function test_job_sends_on_demand_marks_success_and_has_required_retry_policy(): void
    {
        Notification::fake();
        $delivery = $this->deliveryWithItem();
        $job = new SendOrderTransactionalEmail($delivery->id);

        $this->assertInstanceOf(ShouldQueueAfterCommit::class, $job);
        $this->assertInstanceOf(ShouldBeUnique::class, $job);
        $this->assertInstanceOf(ShouldBeUniqueUntilProcessing::class, $job);
        $this->assertSame(3, $job->tries);
        $this->assertSame([60, 300], $job->backoff());
        $this->assertSame((string) $delivery->id, $job->uniqueId());

        $job->handle(app(OrderNotificationDeliveryService::class));

        $delivery->refresh();
        $this->assertSame(OrderNotificationStatus::Sent, $delivery->status);
        $this->assertSame(1, $delivery->attempts);
        $this->assertNotNull($delivery->last_attempt_at);
        $this->assertNotNull($delivery->sent_at);
        $this->assertNull($delivery->failed_at);
        $this->assertNull($delivery->last_error);

        Notification::assertSentOnDemand(
            OrderTransactionalNotification::class,
            function ($notification, array $channels, AnonymousNotifiable $notifiable): bool {
                return $channels === ['mail']
                    && $notifiable->routes['mail'] === [
                        'cliente@example.test' => 'Maria Cliente',
                    ];
            },
        );
    }

    public function test_job_records_retryable_and_final_failures_without_losing_audit_data(): void
    {
        $delivery = $this->deliveryWithItem();
        $exception = new RuntimeException('SMTP temporalmente no disponible');
        $dispatcher = Mockery::mock(Dispatcher::class);
        $dispatcher->shouldReceive('sendNow')->once()->andThrow($exception);
        $this->app->instance(Dispatcher::class, $dispatcher);
        $job = new SendOrderTransactionalEmail($delivery->id);

        try {
            $job->handle(app(OrderNotificationDeliveryService::class));
            $this->fail('El trabajo no propago el fallo SMTP.');
        } catch (RuntimeException $caught) {
            $this->assertSame($exception, $caught);
        }

        $delivery->refresh();
        $this->assertSame(OrderNotificationStatus::Queued, $delivery->status);
        $this->assertSame(1, $delivery->attempts);
        $this->assertSame($exception->getMessage(), $delivery->last_error);
        $this->assertNull($delivery->failed_at);

        $job->failed($exception);

        $delivery->refresh();
        $this->assertSame(OrderNotificationStatus::Failed, $delivery->status);
        $this->assertNotNull($delivery->failed_at);
        $this->assertSame($exception->getMessage(), $delivery->last_error);
        $this->assertDatabaseHas('orders', ['id' => $delivery->order_id]);
    }

    public function test_delivery_never_exceeds_three_smtp_attempts(): void
    {
        Notification::fake();
        $delivery = $this->deliveryWithItem();
        $delivery->applyDeliveryMutation([
            'attempts' => 3,
            'status' => OrderNotificationStatus::Queued,
        ]);

        (new SendOrderTransactionalEmail($delivery->id))
            ->handle(app(OrderNotificationDeliveryService::class));

        $delivery->refresh();
        $this->assertSame(OrderNotificationStatus::Failed, $delivery->status);
        $this->assertSame(3, $delivery->attempts);
        $this->assertNotNull($delivery->failed_at);
        Notification::assertNothingSent();
    }

    public function test_rolled_back_business_transaction_persists_neither_delivery_nor_job(): void
    {
        Queue::fake();
        $order = Order::factory()->create([
            'customer_email' => 'rollback@example.test',
        ]);

        DB::beginTransaction();

        try {
            app(OrderNotificationDeliveryService::class)->record(
                $order,
                OrderNotificationType::Created,
            );
        } finally {
            DB::rollBack();
        }

        $this->assertDatabaseCount('order_notification_deliveries', 0);
        Queue::assertNothingPushed();
        $this->assertDatabaseHas('orders', ['id' => $order->id]);
    }

    public function test_messages_use_order_snapshots_and_configured_brand_for_each_event(): void
    {
        config(['app.name' => 'Tienda Demo']);
        $created = $this->deliveryWithItem();
        $order = $created->order;

        $createdMail = (new OrderTransactionalNotification($created))
            ->toMail(new AnonymousNotifiable);

        $this->assertSame(
            "Recibimos tu pedido {$order->code} | Tienda Demo",
            $createdMail->subject,
        );
        $this->assertSame([
            'html' => 'emails.orders.transactional',
            'text' => 'emails.orders.transactional-text',
        ], $createdMail->view);
        $this->assertSame('Tienda Demo', $createdMail->viewData['brand']);
        $this->assertSame('S/ 118.00', $createdMail->viewData['products_subtotal']);
        $this->assertSame('S/ 10.00', $createdMail->viewData['discount']);
        $this->assertSame('S/ 8.00', $createdMail->viewData['shipping']);
        $this->assertSame('S/ 116.00', $createdMail->viewData['total']);
        $this->assertSame(route('account.orders.show', $order->code), $createdMail->viewData['action_url']);
        $this->assertSame(2, $createdMail->viewData['items'][0]['quantity']);
        $this->assertSame('S/ 59.00', $createdMail->viewData['items'][0]['unit_price']);
        $this->assertSame('S/ 118.00', $createdMail->viewData['items'][0]['line_subtotal']);
        $this->assertTrue($createdMail->viewData['items'][0]['has_multiple_units']);

        foreach ([
            [OrderNotificationType::Cancelled, 'fue cancelado'],
            [OrderNotificationType::Expired, 'vencio antes de completar el pago'],
            [OrderNotificationType::Shipped, 'esta en camino'],
            [OrderNotificationType::PickupReady, 'disponible para recoger'],
            [OrderNotificationType::Delivered, 'fue entregado'],
            [OrderNotificationType::PickedUp, 'fue recogido'],
            [OrderNotificationType::PickupMidpointReminder, 'sigue disponible para recoger'],
            [OrderNotificationType::Pickup48HoursReminder, 'Quedan 48 horas'],
            [OrderNotificationType::PickupDeadlineReminder, 'plazo para recoger'],
        ] as [$type, $expectedText]) {
            $delivery = OrderNotificationDelivery::factory()
                ->for($order)
                ->create([
                    'type' => $type,
                    'recipient_email' => "{$type->value}@example.test",
                ]);
            $mail = (new OrderTransactionalNotification($delivery->load('order.items')))
                ->toMail(new AnonymousNotifiable);

            $this->assertStringContainsString($expectedText, $mail->viewData['summary']);
            $this->assertStringContainsString('Tienda Demo', $mail->subject);
            $this->assertFalse($mail->viewData['has_items']);
            $this->assertSame([], $mail->viewData['embedded_images']);
        }
    }

    public function test_thumbnail_is_local_square_jpeg_and_uses_placeholder_for_unsafe_sources(): void
    {
        Storage::fake('public');
        Http::preventStrayRequests();
        Storage::disk('public')->put('products/email-source.png', $this->png(180, 120));
        $service = app(OrderEmailThumbnailService::class);

        $local = $service->make('products/email-source.png');
        $missing = $service->make('products/missing.webp');
        $remote = $service->make('https://images.example.test/product.webp');
        $size = getimagesizefromstring($local->contents);

        $this->assertIsArray($size);
        $this->assertSame(OrderEmailThumbnailService::SIZE, $size[0]);
        $this->assertSame(OrderEmailThumbnailService::SIZE, $size[1]);
        $this->assertSame('image/jpeg', $size['mime']);
        $this->assertLessThanOrEqual(OrderEmailThumbnailService::TARGET_MAX_BYTES, $local->bytes());
        $this->assertSame($missing->fingerprint, $remote->fingerprint);
        $this->assertNotSame($local->fingerprint, $missing->fingerprint);
        Http::assertNothingSent();
    }

    public function test_cancelled_email_exposes_reason_and_refund_without_admin_identity(): void
    {
        $customer = User::factory()->create();
        $admin = User::factory()->admin()->create([
            'name' => 'Administrador Interno',
            'email' => 'interno@example.test',
        ]);
        $order = Order::factory()->for($customer)->create([
            'order_status' => OrderStatus::Cancelled,
            'payment_status' => PaymentStatus::RefundPending,
            'delivery_status' => DeliveryStatus::Cancelled,
            'cancelled_at' => now(),
        ]);
        OrderStatusHistory::factory()->for($order)->create([
            'domain' => OrderHistoryDomain::Order,
            'from_status' => OrderStatus::Processing->value,
            'to_status' => OrderStatus::Cancelled->value,
            'actor_id' => $admin->id,
            'actor_name' => $admin->name,
            'actor_email' => $admin->email,
            'reason' => 'El producto no supero el control de calidad',
            'metadata' => ['source' => 'admin'],
        ]);
        $delivery = OrderNotificationDelivery::factory()->for($order)->create([
            'type' => OrderNotificationType::Cancelled,
        ]);

        $mail = (new OrderTransactionalNotification($delivery))
            ->toMail(new AnonymousNotifiable);
        $text = view('emails.orders.transactional-text', $mail->viewData)->render();
        $serializedData = json_encode($mail->viewData, JSON_THROW_ON_ERROR);

        $this->assertSame('Pedido cancelado por la tienda', $mail->viewData['cancellation']['title']);
        $this->assertSame('El producto no supero el control de calidad', $mail->viewData['cancellation']['reason']);
        $this->assertSame(
            'El reembolso al medio de pago original esta pendiente de confirmacion.',
            $mail->viewData['cancellation']['refund_message'],
        );
        $this->assertStringContainsString('Motivo: El producto no supero el control de calidad', $text);
        $this->assertStringNotContainsString($admin->name, $serializedData);
        $this->assertStringNotContainsString($admin->email, $serializedData);
    }

    public function test_trusted_remote_image_is_embedded_and_cached_without_a_second_download(): void
    {
        Storage::fake('local');
        config(['mail.order_images.remote_hosts' => ['images.example.test']]);
        $source = $this->png(180, 120);
        Http::fake([
            'https://images.example.test/*' => Http::response($source, 200, [
                'Content-Type' => 'image/png',
                'Content-Length' => (string) strlen($source),
            ]),
        ]);
        $service = app(OrderEmailThumbnailService::class);
        $url = 'https://images.example.test/products/omega.png';

        $first = $service->make($url);
        $second = $service->make($url);
        $size = getimagesizefromstring($first->contents);

        $this->assertSame($first->fingerprint, $second->fingerprint);
        $this->assertIsArray($size);
        $this->assertSame('image/jpeg', $size['mime']);
        $this->assertSame(96, $size[0]);
        $this->assertSame(96, $size[1]);
        Storage::disk('local')->assertExists(
            'order-email-thumbnails/'.hash('sha256', $url).'.jpg',
        );
        Http::assertSentCount(1);
    }

    public function test_rendered_email_embeds_deduplicated_cid_images_and_has_plain_text_fallback(): void
    {
        $delivery = $this->deliveryWithItem();
        $order = $delivery->order;
        OrderItem::factory()->for($order)->create([
            'product_name' => 'Vitamina C',
            'product_presentation' => '60 capsulas',
            'product_image' => $order->items->first()->product_image,
            'quantity' => 1,
            'unit_price_cents' => 2_500,
            'gross_total_cents' => 2_500,
            'discount_cents' => 0,
            'net_value_cents' => 2_119,
            'tax_cents' => 381,
            'total_cents' => 2_500,
        ]);
        $delivery->load('order.items');
        $transport = $this->arrayTransport();

        Notification::route('mail', [
            'cliente@example.test' => 'Maria Cliente',
        ])->notifyNow(new OrderTransactionalNotification($delivery));

        $sent = $transport->messages()->sole();
        $email = $sent->getOriginalMessage();

        $this->assertInstanceOf(Email::class, $email);
        $html = (string) $email->getHtmlBody();
        $text = (string) $email->getTextBody();
        $attachments = $email->getAttachments();

        $this->assertStringContainsString('cid:', $html);
        $this->assertStringNotContainsString('data:image', $html);
        $this->assertStringNotContainsString('<script', strtolower($html));
        $this->assertSame(1, substr_count(strtolower($html), '<a '));
        $this->assertStringContainsString('2 x S/ 59.00', $html);
        $this->assertStringContainsString('S/ 118.00', $html);
        $this->assertStringContainsString('Vitamina C', $html);
        $this->assertStringContainsString('2 x S/ 59.00 = S/ 118.00', $text);
        $this->assertStringContainsString('Vitamina C: S/ 25.00', $text);
        $this->assertStringContainsString('Entrega: S/ 8.00', $text);
        $this->assertCount(1, $attachments);
        $this->assertSame('inline', $attachments[0]->getDisposition());
        $this->assertSame('image', $attachments[0]->getMediaType());
        $this->assertSame('jpeg', $attachments[0]->getMediaSubtype());
        $this->assertLessThanOrEqual(
            OrderEmailThumbnailService::TARGET_MAX_BYTES,
            strlen($attachments[0]->getBody()),
        );
    }

    public function test_created_email_templates_tolerate_absent_cancellation_data(): void
    {
        $delivery = OrderNotificationDelivery::factory()
            ->create(['type' => OrderNotificationType::Created])
            ->load('order.items');
        $data = app(OrderTransactionalEmailPresenter::class)->present($delivery);
        unset($data['cancellation']);

        $html = view('emails.orders.transactional', $data)->render();
        $text = view('emails.orders.transactional-text', $data)->render();

        $this->assertStringContainsString('Pedido recibido', $html);
        $this->assertStringContainsString('Pedido recibido', $text);
        $this->assertStringNotContainsString('Motivo:', $html);
        $this->assertStringNotContainsString('Motivo:', $text);
    }

    public function test_presenter_limits_total_embedded_image_weight_and_reuses_fallback(): void
    {
        $order = Order::factory()->create();

        foreach (range(1, 15) as $index) {
            OrderItem::factory()->for($order)->create([
                'product_name' => "Producto {$index}",
                'product_image' => "products/product-{$index}.webp",
            ]);
        }

        $delivery = OrderNotificationDelivery::factory()
            ->for($order)
            ->create(['type' => OrderNotificationType::Created])
            ->load('order.items');
        $sequence = 0;
        $thumbnails = Mockery::mock(OrderEmailThumbnailService::class);
        $thumbnails->shouldReceive('make')
            ->andReturnUsing(function (?string $path) use (&$sequence): OrderEmailThumbnail {
                if ($path === null) {
                    return new OrderEmailThumbnail(
                        str_repeat('p', 10_000),
                        'fallback',
                        'fallback.jpg',
                    );
                }

                $sequence++;

                return new OrderEmailThumbnail(
                    str_repeat(chr(64 + $sequence), 25_000),
                    "image-{$sequence}",
                    "image-{$sequence}.jpg",
                );
            });
        $presenter = new OrderTransactionalEmailPresenter(
            $thumbnails,
            app(CustomerOrderDateFormatter::class),
            app(OrderCancellationDetailsResolver::class),
        );

        $data = $presenter->present($delivery);

        $this->assertLessThanOrEqual(
            OrderTransactionalEmailPresenter::MAX_EMBEDDED_IMAGE_BYTES,
            $data['embedded_image_bytes'],
        );
        $this->assertCount(12, $data['embedded_images']);
        $this->assertSame('fallback', $data['items'][11]['image_key']);
        $this->assertSame('fallback', $data['items'][14]['image_key']);
    }

    private function deliveryWithItem(): OrderNotificationDelivery
    {
        $user = User::factory()->create(['email' => 'cliente@example.test']);
        $order = Order::factory()->for($user)->create([
            'customer_name' => 'Maria Cliente',
            'customer_email' => 'cliente@example.test',
            'products_subtotal_cents' => 11_800,
            'discount_cents' => 1_000,
            'shipping_fee_cents' => 800,
            'shipping_net_value_cents' => 678,
            'shipping_tax_cents' => 122,
            'taxable_value_cents' => 9_831,
            'net_value_cents' => 9_831,
            'tax_cents' => 1_769,
            'total_cents' => 11_600,
            'reservation_expires_at' => now()->addMinutes(15),
        ]);
        OrderItem::factory()->for($order)->create([
            'product_name' => 'Omega 3 Historico',
            'quantity' => 2,
            'unit_price_cents' => 5_900,
            'gross_total_cents' => 11_800,
            'discount_cents' => 1_000,
            'net_value_cents' => 9_153,
            'tax_cents' => 1_647,
            'total_cents' => 10_800,
        ]);

        return OrderNotificationDelivery::factory()
            ->for($order)
            ->create([
                'type' => OrderNotificationType::Created,
                'recipient_email' => 'cliente@example.test',
                'recipient_name' => 'Maria Cliente',
            ])
            ->load('order.items');
    }

    private function arrayTransport(): ArrayTransport
    {
        $transport = app(MailManager::class)->mailer()->getSymfonyTransport();
        $this->assertInstanceOf(ArrayTransport::class, $transport);
        $transport->flush();

        return $transport;
    }

    private function png(int $width, int $height): string
    {
        $image = imagecreatetruecolor($width, $height);
        $background = imagecolorallocate($image, 235, 242, 230);
        $accent = imagecolorallocate($image, 44, 111, 63);
        imagefill($image, 0, 0, $background);
        imagefilledrectangle($image, 20, 20, $width - 20, $height - 20, $accent);
        ob_start();
        imagepng($image);
        $png = ob_get_clean();
        imagedestroy($image);

        $this->assertIsString($png);

        return $png;
    }
}
