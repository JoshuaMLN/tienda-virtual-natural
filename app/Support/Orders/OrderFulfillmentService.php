<?php

namespace App\Support\Orders;

use App\Enums\DeliveryAttemptAttribution;
use App\Enums\DeliveryAttemptResult;
use App\Enums\DeliveryMethod;
use App\Enums\DeliveryStatus;
use App\Enums\DeliveryTrackingStatus;
use App\Enums\OrderHistoryDomain;
use App\Enums\OrderStatus;
use App\Enums\PaymentStatus;
use App\Models\DeliveryAttempt;
use App\Models\Order;
use App\Models\User;
use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;
use DomainException;
use Illuminate\Database\DeadlockException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class OrderFulfillmentService
{
    public function __construct(
        private readonly OrderStateTransitionService $states,
    ) {}

    public function canRecordDeliveryAttempt(Order $order): bool
    {
        return $order->delivery_method === DeliveryMethod::HomeDelivery
            && $order->payment_status === PaymentStatus::Paid
            && $order->order_status === OrderStatus::Processing
            && $order->delivery_status === DeliveryStatus::Shipped
            && $order->delivery_tracking_status === DeliveryTrackingStatus::Active;
    }

    /** @param array<string, mixed> $metadata */
    public function markShipped(Order $order, User $actor, array $metadata): Order
    {
        $order = $this->states->transitionDelivery(
            $order,
            DeliveryStatus::Shipped,
            $actor,
            metadata: $metadata,
        );
        $order->applyFulfillmentMutation([
            'delivery_tracking_status' => DeliveryTrackingStatus::Active,
            'delivery_tracking_completed_at' => null,
        ]);

        return $order->refresh();
    }

    /** @param array<string, mixed> $metadata */
    public function markPickupReady(Order $order, User $actor, array $metadata): Order
    {
        $order = $this->states->transitionDelivery(
            $order,
            DeliveryStatus::ReadyForPickup,
            $actor,
            metadata: $metadata,
        );
        $readyAt = now();
        $order->applyFulfillmentMutation([
            'delivery_tracking_status' => DeliveryTrackingStatus::Active,
            'pickup_ready_at' => $readyAt,
            'pickup_deadline_at' => $readyAt->copy()->addDays($order->pickup_hold_days),
            'delivery_tracking_completed_at' => null,
        ]);

        return $order->refresh();
    }

    /** @param array<string, mixed> $metadata */
    public function confirmPickup(Order $order, User $actor, array $metadata): Order
    {
        $order = $this->states->transitionDelivery(
            $order,
            DeliveryStatus::PickedUp,
            $actor,
            metadata: $metadata,
        );
        $order = $this->states->transitionOrder(
            $order,
            OrderStatus::Completed,
            $actor,
            metadata: $metadata,
        );

        return $this->closeTracking($order);
    }

    public function closeTracking(Order $order): Order
    {
        if ($order->delivery_tracking_status === DeliveryTrackingStatus::Completed) {
            return $order;
        }

        $order->applyFulfillmentMutation([
            'delivery_tracking_status' => DeliveryTrackingStatus::Completed,
            'delivery_tracking_completed_at' => now(),
        ]);

        return $order->refresh();
    }

    public function recordDeliveryAttempt(
        Order $order,
        User $actor,
        string $operationToken,
        DeliveryAttemptResult $result,
        DeliveryAttemptAttribution $attribution,
        string $responsibleName,
        ?string $reason,
        CarbonInterface $occurredAt,
    ): DeliveryAttempt {
        if (! $actor->isAdmin()) {
            throw new DomainException('Solo un administrador puede registrar intentos de entrega.');
        }

        if (! Str::isUuid($operationToken)) {
            throw new DomainException('La referencia del intento de entrega no es valida.');
        }

        for ($attempt = 1; $attempt <= 8; $attempt++) {
            try {
                return DB::transaction(function () use (
                    $order,
                    $actor,
                    $operationToken,
                    $result,
                    $attribution,
                    $responsibleName,
                    $reason,
                    $occurredAt,
                ): DeliveryAttempt {
                    $locked = Order::query()
                        ->whereKey($order->getKey())
                        ->lockForUpdate()
                        ->firstOrFail();

                    $existing = DeliveryAttempt::query()
                        ->where('operation_token', $operationToken)
                        ->first();

                    if ($existing !== null) {
                        if ($existing->order_id !== $locked->getKey()) {
                            throw new DomainException('La referencia del intento ya pertenece a otro pedido.');
                        }

                        return $existing;
                    }

                    if (! $this->canRecordDeliveryAttempt($locked)) {
                        throw new DomainException($this->unavailableAttemptMessage($locked));
                    }

                    $occurredAt = CarbonImmutable::instance($occurredAt)->setTimezone(config('app.timezone'));
                    $this->validateAttemptData(
                        $locked,
                        $result,
                        $attribution,
                        $responsibleName,
                        $reason,
                        $occurredAt,
                    );

                    $cycle = $locked->delivery_current_cycle;
                    $attemptNumber = ((int) DeliveryAttempt::query()
                        ->where('order_id', $locked->getKey())
                        ->where('cycle', $cycle)
                        ->max('attempt_number')) + 1;
                    $consumesAttempt = $result === DeliveryAttemptResult::Incident
                        && $attribution->consumesAttempt();
                    $countedAttempts = DeliveryAttempt::query()
                        ->where('order_id', $locked->getKey())
                        ->where('cycle', $cycle)
                        ->where('consumes_attempt', true)
                        ->count();
                    $countedAttemptNumber = $consumesAttempt ? $countedAttempts + 1 : null;

                    $deliveryAttempt = DeliveryAttempt::query()->create([
                        'order_id' => $locked->getKey(),
                        'operation_token' => $operationToken,
                        'cycle' => $cycle,
                        'attempt_number' => $attemptNumber,
                        'counted_attempt_number' => $countedAttemptNumber,
                        'result' => $result,
                        'attribution' => $result === DeliveryAttemptResult::Delivered
                            ? DeliveryAttemptAttribution::Unattributed
                            : $attribution,
                        'consumes_attempt' => $consumesAttempt,
                        'responsible_name' => trim($responsibleName),
                        'reason' => $result === DeliveryAttemptResult::Incident ? trim((string) $reason) : null,
                        'occurred_at' => $occurredAt,
                        'recorded_by_id' => $actor->getKey(),
                        'recorded_by_name' => $actor->name,
                        'recorded_by_email' => $actor->email,
                    ]);

                    if ($result === DeliveryAttemptResult::Delivered) {
                        $metadata = [
                            'source' => 'admin',
                            'action' => 'record_delivery_attempt',
                            'delivery_attempt_id' => $deliveryAttempt->getKey(),
                            'operation_reference' => $operationToken,
                        ];
                        $locked = $this->states->transitionDelivery(
                            $locked,
                            DeliveryStatus::Delivered,
                            $actor,
                            metadata: $metadata,
                        );
                        $locked = $this->states->transitionOrder(
                            $locked,
                            OrderStatus::Completed,
                            $actor,
                            metadata: $metadata,
                        );
                        $this->closeTracking($locked);
                    } elseif ($consumesAttempt
                        && $countedAttemptNumber >= $locked->delivery_attempts_per_cycle) {
                        $this->closeCurrentCycle($locked, $occurredAt);
                    }

                    return $deliveryAttempt->refresh();
                }, 5);
            } catch (DeadlockException $exception) {
                if ($attempt === 8) {
                    throw $exception;
                }

                usleep($attempt * 50_000);
            }
        }

        throw new DomainException('No se pudo registrar el intento de entrega.');
    }

    public function reconcileFollowUps(int $batchSize = 100): int
    {
        $batchSize = min(500, max(1, $batchSize));
        $ids = Order::query()
            ->where(function ($query): void {
                $query->where(function ($query): void {
                    $query->where('delivery_tracking_status', DeliveryTrackingStatus::Active->value)
                        ->where('delivery_method', DeliveryMethod::Pickup->value)
                        ->where('delivery_status', DeliveryStatus::ReadyForPickup->value)
                        ->whereNotNull('pickup_deadline_at')
                        ->where('pickup_deadline_at', '<=', now());
                })->orWhere(function ($query): void {
                    $query->where('delivery_tracking_status', DeliveryTrackingStatus::AwaitingReshipmentPayment->value)
                        ->whereNotNull('reshipment_payment_due_at')
                        ->where('reshipment_payment_due_at', '<=', now());
                });
            })
            ->orderBy('id')
            ->limit($batchSize)
            ->pluck('id');
        $reconciled = 0;

        foreach ($ids as $id) {
            $changed = DB::transaction(function () use ($id): bool {
                $order = Order::query()->whereKey($id)->lockForUpdate()->first();

                if ($order === null || ! $this->followUpIsDue($order)) {
                    return false;
                }

                $order->applyFulfillmentMutation([
                    'delivery_tracking_status' => DeliveryTrackingStatus::ManualFollowUp,
                    'delivery_manual_follow_up_at' => now(),
                ]);

                return true;
            }, 5);

            if ($changed) {
                $reconciled++;
            }
        }

        return $reconciled;
    }

    private function closeCurrentCycle(Order $order, CarbonInterface $occurredAt): void
    {
        if ($order->delivery_current_cycle < $order->delivery_max_automatic_cycles) {
            $order->applyFulfillmentMutation([
                'delivery_tracking_status' => DeliveryTrackingStatus::AwaitingReshipmentPayment,
                'reshipment_payment_due_at' => CarbonImmutable::instance($occurredAt)
                    ->addDays($order->reshipment_payment_days),
            ]);

            return;
        }

        $order->applyFulfillmentMutation([
            'delivery_tracking_status' => DeliveryTrackingStatus::ManualFollowUp,
            'delivery_manual_follow_up_at' => now(),
        ]);
    }

    private function validateAttemptData(
        Order $order,
        DeliveryAttemptResult $result,
        DeliveryAttemptAttribution $attribution,
        string $responsibleName,
        ?string $reason,
        CarbonInterface $occurredAt,
    ): void {
        $responsibleName = trim($responsibleName);
        $reason = trim((string) $reason);

        if ($responsibleName === '') {
            throw new DomainException('Debes identificar al responsable o transportista.');
        }

        if (mb_strlen($responsibleName) > 120) {
            throw new DomainException('El responsable no puede superar los 120 caracteres.');
        }

        if ($occurredAt->isFuture()) {
            throw new DomainException('La fecha del intento no puede estar en el futuro.');
        }

        $shippedAt = $order->statusHistories()
            ->where('domain', OrderHistoryDomain::Delivery->value)
            ->where('to_status', DeliveryStatus::Shipped->value)
            ->oldest('created_at')
            ->oldest('id')
            ->value('created_at');

        if ($shippedAt !== null && $occurredAt->lt(CarbonImmutable::parse($shippedAt))) {
            throw new DomainException('La fecha del intento no puede ser anterior al despacho.');
        }

        if ($result === DeliveryAttemptResult::Incident) {
            if (mb_strlen($reason) < 5) {
                throw new DomainException('Describe el motivo de la incidencia con al menos 5 caracteres.');
            }

            if (mb_strlen($reason) > 500) {
                throw new DomainException('El motivo de la incidencia no puede superar los 500 caracteres.');
            }
        }

        if ($result === DeliveryAttemptResult::Delivered
            && $attribution !== DeliveryAttemptAttribution::Unattributed) {
            throw new DomainException('Una entrega exitosa no debe tener una atribucion de incidencia.');
        }
    }

    private function unavailableAttemptMessage(Order $order): string
    {
        return match ($order->delivery_tracking_status) {
            DeliveryTrackingStatus::AwaitingReshipmentPayment => 'El ciclo esta cerrado hasta confirmar un nuevo pago de envio.',
            DeliveryTrackingStatus::ManualFollowUp => 'El pedido se encuentra en seguimiento manual.',
            DeliveryTrackingStatus::Completed => 'El seguimiento de esta entrega ya finalizo.',
            default => 'Solo se pueden registrar resultados para pedidos pagados que estan en camino.',
        };
    }

    private function followUpIsDue(Order $order): bool
    {
        if ($order->delivery_tracking_status === DeliveryTrackingStatus::Active) {
            return $order->delivery_method === DeliveryMethod::Pickup
                && $order->delivery_status === DeliveryStatus::ReadyForPickup
                && $order->pickup_deadline_at?->lte(now()) === true;
        }

        return $order->delivery_tracking_status === DeliveryTrackingStatus::AwaitingReshipmentPayment
            && $order->reshipment_payment_due_at?->lte(now()) === true;
    }
}
