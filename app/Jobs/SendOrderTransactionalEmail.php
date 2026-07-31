<?php

namespace App\Jobs;

use App\Notifications\OrderTransactionalNotification;
use App\Support\Orders\Notifications\OrderNotificationDeliveryService;
use Illuminate\Contracts\Queue\ShouldBeUniqueUntilProcessing;
use Illuminate\Contracts\Queue\ShouldQueueAfterCommit;
use Illuminate\Foundation\Queue\Queueable as FoundationQueueable;
use Illuminate\Support\Facades\Notification;
use Throwable;

class SendOrderTransactionalEmail implements ShouldBeUniqueUntilProcessing, ShouldQueueAfterCommit
{
    use FoundationQueueable;

    private const PREDECESSOR_RECHECK_SECONDS = 35;

    public int $tries = 3;

    public int $timeout = 30;

    public int $uniqueFor = 900;

    public function __construct(
        public readonly int $deliveryId,
    ) {}

    /** @return list<int> */
    public function backoff(): array
    {
        return [60, 300];
    }

    public function uniqueId(): string
    {
        return (string) $this->deliveryId;
    }

    public function handle(OrderNotificationDeliveryService $deliveries): void
    {
        if ($deliveries->shouldDeferAttempt($this->deliveryId)) {
            self::dispatch($this->deliveryId)
                ->delay(now()->addSeconds(self::PREDECESSOR_RECHECK_SECONDS));

            return;
        }

        $delivery = $deliveries->beginAttempt($this->deliveryId);

        if ($delivery === null) {
            return;
        }

        try {
            Notification::route('mail', [
                $delivery->recipient_email => $delivery->recipient_name,
            ])->notifyNow(new OrderTransactionalNotification($delivery));
        } catch (Throwable $exception) {
            $deliveries->markRetryableFailure($this->deliveryId, $exception);

            throw $exception;
        }

        $deliveries->markSent($this->deliveryId);
    }

    public function failed(?Throwable $exception): void
    {
        if ($exception !== null) {
            app(OrderNotificationDeliveryService::class)->markFailed(
                $this->deliveryId,
                $exception,
            );
        }
    }
}
