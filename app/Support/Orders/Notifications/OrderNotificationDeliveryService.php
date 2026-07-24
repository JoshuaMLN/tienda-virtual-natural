<?php

namespace App\Support\Orders\Notifications;

use App\Enums\OrderNotificationStatus;
use App\Enums\OrderNotificationType;
use App\Jobs\SendOrderTransactionalEmail;
use App\Models\Order;
use App\Models\OrderNotificationDelivery;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;
use Throwable;

class OrderNotificationDeliveryService
{
    public function __construct(
        private readonly OrderNotificationRecipientResolver $recipients,
    ) {}

    /**
     * Freeze the recipients and queue one auditable delivery per address.
     *
     * @return Collection<int, OrderNotificationDelivery>
     */
    public function record(Order $order, OrderNotificationType $type): Collection
    {
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
                $this->dispatchAfterCommit((int) $delivery->getKey());
            }

            return $deliveries;
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

            return $delivery->load('order.items');
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

            if ($delivery === null || $delivery->status === OrderNotificationStatus::Sent) {
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
}
