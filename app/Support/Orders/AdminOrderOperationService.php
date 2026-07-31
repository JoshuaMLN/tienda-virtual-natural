<?php

namespace App\Support\Orders;

use App\Enums\AdminOrderAction;
use App\Enums\DeliveryMethod;
use App\Enums\DeliveryStatus;
use App\Enums\OrderNotificationType;
use App\Enums\OrderStatus;
use App\Enums\PaymentStatus;
use App\Models\Order;
use App\Models\User;
use App\Support\Orders\Notifications\OrderNotificationDeliveryService;
use App\Support\Orders\Reservations\StockReservationService;
use DomainException;
use Illuminate\Database\DeadlockException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class AdminOrderOperationService
{
    public function __construct(
        private readonly OrderStateTransitionService $states,
        private readonly StockReservationService $reservations,
        private readonly OrderNotificationDeliveryService $notifications,
    ) {}

    /** @return list<AdminOrderAction> */
    public function availableActions(Order $order): array
    {
        if ($this->isClosed($order)) {
            return [];
        }

        $actions = [];

        if ($this->canStartPreparation($order)) {
            $actions[] = AdminOrderAction::StartPreparation;
        } elseif ($this->canMarkShipped($order)) {
            $actions[] = AdminOrderAction::MarkShipped;
        } elseif ($this->canMarkReadyForPickup($order)) {
            $actions[] = AdminOrderAction::MarkReadyForPickup;
        } elseif ($this->canConfirmDelivery($order)) {
            $actions[] = AdminOrderAction::ConfirmDelivery;
        } elseif ($this->canConfirmPickup($order)) {
            $actions[] = AdminOrderAction::ConfirmPickup;
        }

        if ($this->canCancel($order)) {
            $actions[] = AdminOrderAction::Cancel;
        }

        return $actions;
    }

    public function perform(
        Order $order,
        AdminOrderAction $action,
        User $actor,
        ?string $reason = null,
    ): Order {
        if (! $actor->isAdmin()) {
            throw new DomainException('Solo un administrador puede operar pedidos.');
        }

        for ($attempt = 1; $attempt <= 8; $attempt++) {
            try {
                return DB::transaction(function () use ($order, $action, $actor, $reason): Order {
                    $locked = Order::query()
                        ->whereKey($order->getKey())
                        ->lockForUpdate()
                        ->firstOrFail();

                    if ($this->isAlreadyApplied($locked, $action)) {
                        return $locked;
                    }

                    if (! in_array($action, $this->availableActions($locked), true)) {
                        throw new DomainException('Esta accion ya no esta disponible para el estado actual del pedido.');
                    }

                    $reason = $this->validatedReason($action, $reason);
                    $metadata = [
                        'source' => 'admin',
                        'action' => $action->value,
                        'operation_reference' => (string) Str::uuid(),
                    ];

                    return match ($action) {
                        AdminOrderAction::StartPreparation => $this->startPreparation($locked, $actor, $metadata),
                        AdminOrderAction::MarkShipped => $this->states->transitionDelivery(
                            $locked,
                            DeliveryStatus::Shipped,
                            $actor,
                            metadata: $metadata,
                        ),
                        AdminOrderAction::MarkReadyForPickup => $this->states->transitionDelivery(
                            $locked,
                            DeliveryStatus::ReadyForPickup,
                            $actor,
                            metadata: $metadata,
                        ),
                        AdminOrderAction::ConfirmDelivery => $this->complete(
                            $locked,
                            DeliveryStatus::Delivered,
                            $actor,
                            $metadata,
                        ),
                        AdminOrderAction::ConfirmPickup => $this->complete(
                            $locked,
                            DeliveryStatus::PickedUp,
                            $actor,
                            $metadata,
                        ),
                        AdminOrderAction::Cancel => $this->cancel($locked, $actor, $reason, $metadata),
                    };
                }, 5);
            } catch (DeadlockException $exception) {
                if ($attempt === 8) {
                    throw $exception;
                }

                usleep($attempt * 50_000);
            }
        }

        throw new DomainException('No se pudo completar la operacion del pedido.');
    }

    /** @param array<string, mixed> $metadata */
    private function startPreparation(Order $order, User $actor, array $metadata): Order
    {
        if ($order->order_status === OrderStatus::PendingPayment) {
            $order = $this->states->transitionOrder(
                $order,
                OrderStatus::Processing,
                $actor,
                metadata: $metadata,
            );
        }

        return $this->states->transitionDelivery(
            $order,
            DeliveryStatus::Preparing,
            $actor,
            metadata: $metadata,
        );
    }

    /** @param array<string, mixed> $metadata */
    private function complete(
        Order $order,
        DeliveryStatus $deliveryStatus,
        User $actor,
        array $metadata,
    ): Order {
        $order = $this->states->transitionDelivery(
            $order,
            $deliveryStatus,
            $actor,
            metadata: $metadata,
        );

        return $this->states->transitionOrder(
            $order,
            OrderStatus::Completed,
            $actor,
            metadata: $metadata,
        );
    }

    /** @param array<string, mixed> $metadata */
    private function cancel(
        Order $order,
        User $actor,
        string $reason,
        array $metadata,
    ): Order {
        if (in_array($order->payment_status, [PaymentStatus::Pending, PaymentStatus::Failed], true)) {
            $cancelled = $this->reservations->releaseForCancellation(
                $order,
                $actor,
                $reason,
                $metadata,
            );
            $this->notifications->record($cancelled, OrderNotificationType::Cancelled);

            return $cancelled;
        }

        $this->reservations->restockConsumedForCancellation(
            $order,
            $actor,
            $reason,
            $metadata['operation_reference'],
        );

        $order = $this->states->transitionDelivery(
            $order,
            DeliveryStatus::Cancelled,
            $actor,
            $reason,
            $metadata,
        );
        $order = $this->states->transitionPayment(
            $order,
            PaymentStatus::RefundPending,
            $actor,
            $reason,
            $metadata,
        );

        $cancelled = $this->states->transitionOrder(
            $order,
            OrderStatus::Cancelled,
            $actor,
            $reason,
            $metadata,
        );
        $this->notifications->record($cancelled, OrderNotificationType::Cancelled);

        return $cancelled;
    }

    private function validatedReason(AdminOrderAction $action, ?string $reason): string
    {
        $reason = trim((string) $reason);

        if ($action->requiresReason() && $reason === '') {
            throw new DomainException('Debes registrar el motivo de la cancelacion.');
        }

        return $reason;
    }

    private function isClosed(Order $order): bool
    {
        return in_array($order->order_status, [
            OrderStatus::Completed,
            OrderStatus::Cancelled,
            OrderStatus::Expired,
        ], true);
    }

    private function canStartPreparation(Order $order): bool
    {
        return $order->payment_status === PaymentStatus::Paid
            && in_array($order->order_status, [OrderStatus::PendingPayment, OrderStatus::Processing], true)
            && $order->delivery_status === DeliveryStatus::Pending;
    }

    private function canMarkShipped(Order $order): bool
    {
        return $order->delivery_method === DeliveryMethod::HomeDelivery
            && $order->payment_status === PaymentStatus::Paid
            && $order->order_status === OrderStatus::Processing
            && $order->delivery_status === DeliveryStatus::Preparing;
    }

    private function canMarkReadyForPickup(Order $order): bool
    {
        return $order->delivery_method === DeliveryMethod::Pickup
            && $order->payment_status === PaymentStatus::Paid
            && $order->order_status === OrderStatus::Processing
            && $order->delivery_status === DeliveryStatus::Preparing;
    }

    private function canConfirmDelivery(Order $order): bool
    {
        return $order->delivery_method === DeliveryMethod::HomeDelivery
            && $order->payment_status === PaymentStatus::Paid
            && $order->order_status === OrderStatus::Processing
            && $order->delivery_status === DeliveryStatus::Shipped;
    }

    private function canConfirmPickup(Order $order): bool
    {
        return $order->delivery_method === DeliveryMethod::Pickup
            && $order->payment_status === PaymentStatus::Paid
            && $order->order_status === OrderStatus::Processing
            && $order->delivery_status === DeliveryStatus::ReadyForPickup;
    }

    private function canCancel(Order $order): bool
    {
        if (! in_array($order->order_status, [OrderStatus::PendingPayment, OrderStatus::Processing], true)) {
            return false;
        }

        if (in_array($order->payment_status, [PaymentStatus::Pending, PaymentStatus::Failed], true)) {
            return $order->delivery_status === DeliveryStatus::Pending;
        }

        if ($order->payment_status !== PaymentStatus::Paid) {
            return false;
        }

        return match ($order->delivery_method) {
            DeliveryMethod::HomeDelivery => in_array($order->delivery_status, [
                DeliveryStatus::Pending,
                DeliveryStatus::Preparing,
            ], true),
            DeliveryMethod::Pickup => in_array($order->delivery_status, [
                DeliveryStatus::Pending,
                DeliveryStatus::Preparing,
                DeliveryStatus::ReadyForPickup,
            ], true),
        };
    }

    private function isAlreadyApplied(Order $order, AdminOrderAction $action): bool
    {
        return match ($action) {
            AdminOrderAction::StartPreparation => $order->order_status === OrderStatus::Processing
                && $order->delivery_status === DeliveryStatus::Preparing,
            AdminOrderAction::MarkShipped => $order->delivery_method === DeliveryMethod::HomeDelivery
                && in_array($order->delivery_status, [DeliveryStatus::Shipped, DeliveryStatus::Delivered], true),
            AdminOrderAction::MarkReadyForPickup => $order->delivery_method === DeliveryMethod::Pickup
                && in_array($order->delivery_status, [DeliveryStatus::ReadyForPickup, DeliveryStatus::PickedUp], true),
            AdminOrderAction::ConfirmDelivery => $order->order_status === OrderStatus::Completed
                && $order->delivery_status === DeliveryStatus::Delivered,
            AdminOrderAction::ConfirmPickup => $order->order_status === OrderStatus::Completed
                && $order->delivery_status === DeliveryStatus::PickedUp,
            AdminOrderAction::Cancel => $order->order_status === OrderStatus::Cancelled
                && $order->delivery_status === DeliveryStatus::Cancelled,
        };
    }
}
