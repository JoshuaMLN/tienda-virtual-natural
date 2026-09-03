<?php

namespace App\Support\Orders\Notifications;

use App\Enums\DeliveryMethod;
use App\Enums\DeliveryStatus;
use App\Enums\OrderNotificationStatus;
use App\Enums\OrderNotificationType;
use App\Jobs\SendOrderTransactionalEmail;
use App\Models\Order;
use App\Models\OrderNotificationDelivery;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;
use Throwable;

class OrderNotificationDeliveryService
{
    private const STALE_SENDING_AFTER_SECONDS = 120;

    private const SUPERSEDED_BY_CANCELLATION = 'El pedido fue cancelado antes de enviar esta comunicacion.';

    private const SUPERSEDED_BY_EXPIRATION = 'La reserva vencio antes de enviar esta comunicacion.';

    private const SUPERSEDED_BY_PICKUP = 'El pedido fue recogido antes de enviar este recordatorio.';

    public function __construct(
        private readonly OrderNotificationRecipientResolver $recipients,
    ) {}

    /**
     * Freeze the recipients and queue one auditable delivery per address.
     *
     * @return Collection<int, OrderNotificationDelivery>
     */
    public function record(
        Order $order,
        OrderNotificationType $type,
    ): Collection {
        return DB::transaction(function () use ($order, $type): Collection {
            $lockedOrder = Order::query()
                ->whereKey($order->getKey())
                ->lockForUpdate()
                ->firstOrFail();
            $existing = OrderNotificationDelivery::query()
                ->where('order_id', $lockedOrder->getKey())
                ->where('type', $type->value)
                ->orderBy('id')
                ->get();

            if ($existing->isNotEmpty()) {
                $this->supersedeObsoleteCreatedDeliveries($lockedOrder->getKey(), $type);

                return $existing;
            }

            $deliveries = new Collection;

            foreach ($this->recipients->resolve($lockedOrder) as $recipient) {
                $delivery = OrderNotificationDelivery::query()->create([
                    'order_id' => $lockedOrder->getKey(),
                    'type' => $type,
                    'recipient_email' => $recipient->email,
                    'recipient_name' => $recipient->name,
                    'status' => OrderNotificationStatus::Queued,
                    'attempts' => 0,
                    'queued_at' => now(),
                ]);

                $deliveries->push($delivery);
            }

            $this->supersedeObsoleteCreatedDeliveries($lockedOrder->getKey(), $type);

            foreach ($deliveries as $delivery) {
                $this->dispatchAfterCommit((int) $delivery->getKey());
            }

            return $deliveries;
        }, 5);
    }

    public function schedulePickupReminders(Order $order): void
    {
        if ($order->delivery_method !== DeliveryMethod::Pickup
            || $order->delivery_status !== DeliveryStatus::ReadyForPickup
            || $order->pickup_ready_at === null
            || $order->pickup_deadline_at === null) {
            return;
        }

        $readyAt = CarbonImmutable::instance($order->pickup_ready_at);
        $deadlineAt = CarbonImmutable::instance($order->pickup_deadline_at);
        $midpointAt = $readyAt->addSeconds((int) floor($readyAt->diffInSeconds($deadlineAt) / 2));
        $now = CarbonImmutable::now();

        foreach ([
            [OrderNotificationType::PickupMidpointReminder, $midpointAt],
            [OrderNotificationType::Pickup48HoursReminder, $deadlineAt->subHours(48)],
            [OrderNotificationType::PickupDeadlineReminder, $deadlineAt],
        ] as [$type, $dueAt]) {
            if ($dueAt->lte($readyAt)
                || $dueAt->gt($now)
                || $dueAt->lt($now->subMinutes(5))) {
                continue;
            }

            if ($type !== OrderNotificationType::PickupDeadlineReminder
                && $now->gte($deadlineAt)) {
                continue;
            }

            $this->record($order, $type);
        }
    }

    public function reconcilePickupReminders(int $batchSize = 100): int
    {
        $ids = Order::query()
            ->where('delivery_method', DeliveryMethod::Pickup->value)
            ->where('delivery_status', DeliveryStatus::ReadyForPickup->value)
            ->whereNotNull('pickup_ready_at')
            ->whereNotNull('pickup_deadline_at')
            ->orderBy('id')
            ->limit(min(500, max(1, $batchSize)))
            ->pluck('id');

        foreach ($ids as $id) {
            DB::transaction(function () use ($id): void {
                $order = Order::query()->whereKey($id)->lockForUpdate()->first();

                if ($order !== null) {
                    $this->schedulePickupReminders($order);
                }
            }, 5);
        }

        return $ids->count();
    }

    public function cancelPendingPickupReminders(Order $order): void
    {
        $deliveries = OrderNotificationDelivery::query()
            ->where('order_id', $order->getKey())
            ->whereIn('type', array_map(
                fn (OrderNotificationType $type): string => $type->value,
                OrderNotificationType::pickupReminderTypes(),
            ))
            ->lockForUpdate()
            ->get();

        foreach ($deliveries as $delivery) {
            if ($delivery->status !== OrderNotificationStatus::Queued) {
                continue;
            }

            $delivery->applyDeliveryMutation([
                'status' => OrderNotificationStatus::Superseded,
                'superseded_at' => now(),
                'superseded_reason' => self::SUPERSEDED_BY_PICKUP,
                'failed_at' => null,
                'last_error' => null,
            ]);
        }
    }

    public function shouldDeferAttempt(int $deliveryId): bool
    {
        return DB::transaction(function () use ($deliveryId): bool {
            $delivery = OrderNotificationDelivery::query()
                ->whereKey($deliveryId)
                ->lockForUpdate()
                ->first();

            if ($delivery === null
                || ! in_array($delivery->type, [
                    OrderNotificationType::Cancelled,
                    OrderNotificationType::Expired,
                ], true)
                || in_array($delivery->status, [
                    OrderNotificationStatus::Sent,
                    OrderNotificationStatus::Failed,
                    OrderNotificationStatus::Superseded,
                ], true)) {
                return false;
            }

            $createdDeliveries = OrderNotificationDelivery::query()
                ->where('order_id', $delivery->order_id)
                ->where('type', OrderNotificationType::Created->value)
                ->lockForUpdate()
                ->get();

            $this->supersedeQueued($createdDeliveries, $delivery->type);
            $this->supersedeStaleSending($createdDeliveries, $delivery->type);

            return $createdDeliveries->contains(
                fn (OrderNotificationDelivery $created): bool => $created->status === OrderNotificationStatus::Sending,
            );
        }, 5);
    }

    public function beginAttempt(int $deliveryId): ?OrderNotificationDelivery
    {
        return DB::transaction(function () use ($deliveryId): ?OrderNotificationDelivery {
            $delivery = OrderNotificationDelivery::query()
                ->whereKey($deliveryId)
                ->lockForUpdate()
                ->first();

            if ($delivery === null
                || in_array($delivery->status, [
                    OrderNotificationStatus::Sent,
                    OrderNotificationStatus::Failed,
                    OrderNotificationStatus::Superseded,
                ], true)) {
                return null;
            }

            if ($delivery->attempts >= 3) {
                $delivery->applyDeliveryMutation([
                    'status' => OrderNotificationStatus::Failed,
                    'failed_at' => now(),
                    'last_error' => $delivery->last_error ?: 'Se alcanzo el limite de tres intentos.',
                ]);

                return null;
            }

            $delivery->applyDeliveryMutation([
                'status' => OrderNotificationStatus::Sending,
                'attempts' => $delivery->attempts + 1,
                'last_attempt_at' => now(),
                'failed_at' => null,
                'last_error' => null,
            ]);

            return $delivery->load('order.items', 'order.statusHistories');
        }, 5);
    }

    public function markRetryableFailure(int $deliveryId, Throwable $exception): void
    {
        $this->mutateUnlessSent($deliveryId, [
            'status' => OrderNotificationStatus::Queued,
            'last_error' => $this->errorMessage($exception),
        ]);
    }

    public function markSent(int $deliveryId): void
    {
        $this->mutateUnlessSent($deliveryId, [
            'status' => OrderNotificationStatus::Sent,
            'sent_at' => now(),
            'failed_at' => null,
            'last_error' => null,
        ]);
    }

    public function markFailed(int $deliveryId, Throwable $exception): void
    {
        $this->mutateUnlessSent($deliveryId, [
            'status' => OrderNotificationStatus::Failed,
            'failed_at' => now(),
            'last_error' => $this->errorMessage($exception),
        ]);
    }

    /** @param array<string, mixed> $attributes */
    private function mutateUnlessSent(int $deliveryId, array $attributes): void
    {
        DB::transaction(function () use ($deliveryId, $attributes): void {
            $delivery = OrderNotificationDelivery::query()
                ->whereKey($deliveryId)
                ->lockForUpdate()
                ->first();

            if ($delivery === null || in_array($delivery->status, [
                OrderNotificationStatus::Sent,
                OrderNotificationStatus::Superseded,
            ], true)) {
                return;
            }

            $delivery->applyDeliveryMutation($attributes);
        }, 5);
    }

    private function dispatchAfterCommit(int $deliveryId): void
    {
        DB::afterCommit(function () use ($deliveryId): void {
            try {
                SendOrderTransactionalEmail::dispatch($deliveryId);
            } catch (Throwable $exception) {
                report($exception);

                try {
                    $this->markFailed($deliveryId, $exception);
                } catch (Throwable $auditException) {
                    report($auditException);
                }
            }
        });
    }

    private function errorMessage(Throwable $exception): string
    {
        return mb_substr($exception->getMessage(), 0, 5_000);
    }

    private function supersedeObsoleteCreatedDeliveries(
        int $orderId,
        OrderNotificationType $terminalType,
    ): void {
        if (! in_array($terminalType, [
            OrderNotificationType::Cancelled,
            OrderNotificationType::Expired,
        ], true)) {
            return;
        }

        $createdDeliveries = OrderNotificationDelivery::query()
            ->where('order_id', $orderId)
            ->where('type', OrderNotificationType::Created->value)
            ->lockForUpdate()
            ->get();

        $this->supersedeQueued($createdDeliveries, $terminalType);
    }

    /** @param Collection<int, OrderNotificationDelivery> $createdDeliveries */
    private function supersedeQueued(
        Collection $createdDeliveries,
        OrderNotificationType $terminalType,
    ): void {
        $reason = $this->supersededReason($terminalType);

        foreach ($createdDeliveries as $created) {
            if ($created->status !== OrderNotificationStatus::Queued) {
                continue;
            }

            $created->applyDeliveryMutation([
                'status' => OrderNotificationStatus::Superseded,
                'superseded_at' => now(),
                'superseded_reason' => $reason,
                'failed_at' => null,
                'last_error' => null,
            ]);
        }
    }

    /** @param Collection<int, OrderNotificationDelivery> $createdDeliveries */
    private function supersedeStaleSending(
        Collection $createdDeliveries,
        OrderNotificationType $terminalType,
    ): void {
        foreach ($createdDeliveries as $created) {
            if ($created->status !== OrderNotificationStatus::Sending
                || ($created->last_attempt_at !== null
                    && $created->last_attempt_at->isAfter(
                        now()->subSeconds(self::STALE_SENDING_AFTER_SECONDS),
                    ))) {
                continue;
            }

            $created->applyDeliveryMutation([
                'status' => OrderNotificationStatus::Superseded,
                'superseded_at' => now(),
                'superseded_reason' => $this->supersededReason($terminalType),
                'failed_at' => null,
                'last_error' => null,
            ]);
        }
    }

    private function supersededReason(OrderNotificationType $terminalType): string
    {
        return $terminalType === OrderNotificationType::Cancelled
            ? self::SUPERSEDED_BY_CANCELLATION
            : self::SUPERSEDED_BY_EXPIRATION;
    }
}
