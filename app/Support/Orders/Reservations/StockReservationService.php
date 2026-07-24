<?php

namespace App\Support\Orders\Reservations;

use App\Enums\DeliveryStatus;
use App\Enums\OrderHistoryDomain;
use App\Enums\OrderStatus;
use App\Enums\PaymentStatus;
use App\Enums\ReservationStatus;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\StockReservation;
use App\Models\User;
use App\Support\Inventory\InventoryService;
use App\Support\Orders\OrderHistoryRecorder;
use App\Support\Orders\OrderStateTransitionService;
use Carbon\CarbonImmutable;
use DateTimeInterface;
use DomainException;
use Illuminate\Support\Facades\DB;

class StockReservationService
{
    public function __construct(
        private readonly InventoryService $inventory,
        private readonly OrderHistoryRecorder $history,
        private readonly OrderStateTransitionService $states,
    ) {}

    public function reserve(
        OrderItem $orderItem,
        DateTimeInterface $expiresAt,
        ?User $actor = null,
        ?string $operationReference = null,
    ): StockReservation {
        return DB::transaction(function () use ($orderItem, $expiresAt, $actor, $operationReference): StockReservation {
            $lockedItem = OrderItem::query()
                ->with(['order', 'product'])
                ->whereKey($orderItem->getKey())
                ->lockForUpdate()
                ->firstOrFail();

            if ($lockedItem->product === null) {
                throw new DomainException('No se puede reservar stock de un producto eliminado.');
            }

            if ($lockedItem->stockReservation()->exists()) {
                throw new DomainException('El item ya tiene una reserva de stock.');
            }

            $expiration = CarbonImmutable::instance($expiresAt);

            if ($expiration->isPast()) {
                throw new DomainException('La reserva debe vencer en el futuro.');
            }

            $movement = $this->inventory->decrease($lockedItem->product, $lockedItem->quantity, [
                'reason' => 'Reserva de stock',
                'notes' => "Reserva para el pedido {$lockedItem->order->code}",
                'reference' => $lockedItem->order->code,
                'created_by' => $actor?->getKey(),
            ]);

            $reservation = StockReservation::query()->create([
                'order_item_id' => $lockedItem->getKey(),
                'reserve_inventory_movement_id' => $movement->getKey(),
                'quantity' => $lockedItem->quantity,
                'status' => ReservationStatus::Active,
                'expires_at' => $expiration,
            ]);

            $order = $lockedItem->order;
            $currentExpiration = $order->reservation_expires_at;

            if ($currentExpiration === null || $expiration->lessThan($currentExpiration)) {
                $order->applyReservationExpiration($expiration);
            }

            $this->history->record(
                $order,
                OrderHistoryDomain::Reservation,
                null,
                ReservationStatus::Active->value,
                $actor,
                'Stock reservado',
                $this->historyMetadata($reservation, $operationReference),
            );

            return $reservation->refresh();
        });
    }

    public function consume(
        StockReservation $reservation,
        ?User $actor = null,
        ?string $operationReference = null,
    ): StockReservation {
        return DB::transaction(function () use ($reservation, $actor, $operationReference): StockReservation {
            $locked = $this->lock($reservation);

            if ($locked->status === ReservationStatus::Consumed) {
                return $locked;
            }

            $this->ensureActive($locked, ReservationStatus::Consumed);

            if (CarbonImmutable::now()->greaterThanOrEqualTo($locked->expires_at)) {
                throw new InvalidReservationTransitionException(
                    $locked->status->value,
                    ReservationStatus::Consumed->value,
                    'Una reserva vencida ya no se puede consumir.',
                );
            }
            $locked->applyStatusMutation([
                'status' => ReservationStatus::Consumed,
                'consumed_at' => now(),
            ]);
            $this->recordTransition(
                $locked,
                ReservationStatus::Active,
                ReservationStatus::Consumed,
                $actor,
                'Reserva consumida por pago confirmado',
                $operationReference,
            );

            return $locked->refresh();
        });
    }

    public function release(
        StockReservation $reservation,
        string $reason,
        ?User $actor = null,
        ?string $operationReference = null,
    ): StockReservation {
        return $this->returnStock($reservation, ReservationStatus::Released, $reason, $actor, operationReference: $operationReference);
    }

    public function expire(
        StockReservation $reservation,
        ?DateTimeInterface $at = null,
        ?User $actor = null,
        ?string $operationReference = null,
    ): StockReservation {
        $moment = $at ? CarbonImmutable::instance($at) : CarbonImmutable::now();

        return $this->returnStock(
            $reservation,
            ReservationStatus::Expired,
            'Vencimiento de la reserva',
            $actor,
            $moment,
            $operationReference,
        );
    }

    public function consumeForOrder(Order $order, ?User $actor = null): void
    {
        DB::transaction(function () use ($order, $actor): void {
            $lockedOrder = Order::query()->whereKey($order->getKey())->lockForUpdate()->firstOrFail();
            $operationReference = $this->operationReference('consume', $lockedOrder);

            $lockedOrder->stockReservations()
                ->where('status', ReservationStatus::Active->value)
                ->orderBy('stock_reservations.id')
                ->pluck('stock_reservations.id')
                ->each(fn (int $id) => $this->consume(
                    StockReservation::query()->findOrFail($id),
                    $actor,
                    $operationReference,
                ));
        });
    }

    public function releaseForCancellation(Order $order, ?User $actor = null, string $reason = 'Pedido cancelado'): Order
    {
        return DB::transaction(function () use ($order, $actor, $reason): Order {
            $current = Order::query()->whereKey($order->getKey())->lockForUpdate()->firstOrFail();
            $operationReference = $this->operationReference('release', $current);

            $current->stockReservations()
                ->where('status', ReservationStatus::Active->value)
                ->orderBy('stock_reservations.id')
                ->pluck('stock_reservations.id')
                ->each(fn (int $id) => $this->release(
                    StockReservation::query()->findOrFail($id),
                    $reason,
                    $actor,
                    $operationReference,
                ));

            $current = $current->refresh();

            if (in_array($current->delivery_status, [DeliveryStatus::Pending, DeliveryStatus::Preparing, DeliveryStatus::ReadyForPickup], true)) {
                $current = $this->states->transitionDelivery($current, DeliveryStatus::Cancelled, $actor, $reason);
            }

            return $this->states->transitionOrder($current, OrderStatus::Cancelled, $actor, $reason);
        });
    }

    public function expireForOrder(Order $order, ?DateTimeInterface $at = null): Order
    {
        $moment = $at ? CarbonImmutable::instance($at) : CarbonImmutable::now();

        return DB::transaction(function () use ($order, $moment): Order {
            $current = Order::query()->whereKey($order->getKey())->lockForUpdate()->firstOrFail();
            $operationReference = $this->operationReference('expire', $current);

            if (! in_array($current->order_status, [OrderStatus::PendingPayment, OrderStatus::Expired], true)
                || ! in_array($current->payment_status, [PaymentStatus::Pending, PaymentStatus::Failed, PaymentStatus::Expired], true)) {
                throw new DomainException('Solo un pedido pendiente de pago puede vencer y liberar sus reservas.');
            }

            if ($current->reservation_expires_at === null || $moment->lessThan($current->reservation_expires_at)) {
                throw new DomainException('El pedido todavia no ha alcanzado el vencimiento de su reserva.');
            }

            $current->stockReservations()
                ->where('status', ReservationStatus::Active->value)
                ->orderBy('stock_reservations.id')
                ->pluck('stock_reservations.id')
                ->each(fn (int $id) => $this->expire(
                    StockReservation::query()->findOrFail($id),
                    $moment,
                    operationReference: $operationReference,
                ));

            $current = $current->refresh();

            if (in_array($current->payment_status, [PaymentStatus::Pending, PaymentStatus::Failed], true)) {
                $current = $this->states->transitionPayment($current, PaymentStatus::Expired, reason: 'Vencimiento de la reserva');
            }

            if ($current->order_status === OrderStatus::PendingPayment) {
                $current = $this->states->transitionOrder($current, OrderStatus::Expired, reason: 'Vencimiento de la reserva');
            }

            return $current;
        });
    }

    private function returnStock(
        StockReservation $reservation,
        ReservationStatus $target,
        string $reason,
        ?User $actor,
        ?DateTimeInterface $at = null,
        ?string $operationReference = null,
    ): StockReservation {
        return DB::transaction(function () use ($reservation, $target, $reason, $actor, $at, $operationReference): StockReservation {
            $locked = $this->lock($reservation);

            if ($locked->status === $target) {
                return $locked;
            }

            $this->ensureActive($locked, $target);

            $moment = $at ? CarbonImmutable::instance($at) : CarbonImmutable::now();

            if ($target === ReservationStatus::Expired && $moment->lessThan($locked->expires_at)) {
                throw new InvalidReservationTransitionException(
                    $locked->status->value,
                    ReservationStatus::Expired->value,
                    'La reserva todavia no ha alcanzado su fecha de vencimiento.',
                );
            }

            $movementId = null;

            $locked->loadMissing(['orderItem.order', 'orderItem.product']);
            $product = $locked->orderItem->product;
            $order = $locked->orderItem->order;

            if ($product !== null) {
                $movement = $this->inventory->increase($product, $locked->quantity, [
                    'reason' => $target === ReservationStatus::Expired ? 'Liberacion por vencimiento' : 'Liberacion de reserva',
                    'notes' => $reason,
                    'reference' => $order->code,
                    'created_by' => $actor?->getKey(),
                ]);
                $movementId = $movement->getKey();
            }

            $attributes = [
                'status' => $target,
                'release_inventory_movement_id' => $movementId,
                'release_reason' => $reason,
            ];

            if ($target === ReservationStatus::Expired) {
                $attributes['expired_at'] = $moment;
            } else {
                $attributes['released_at'] = $moment;
            }

            $locked->applyStatusMutation($attributes);
            $this->recordTransition(
                $locked,
                ReservationStatus::Active,
                $target,
                $actor,
                $reason,
                $operationReference,
            );

            return $locked->refresh();
        });
    }

    private function ensureActive(StockReservation $reservation, ReservationStatus $target): void
    {
        if ($reservation->status !== ReservationStatus::Active) {
            throw new InvalidReservationTransitionException($reservation->status->value, $target->value);
        }
    }

    private function lock(StockReservation $reservation): StockReservation
    {
        return StockReservation::query()
            ->with(['orderItem.order', 'orderItem.product'])
            ->whereKey($reservation->getKey())
            ->lockForUpdate()
            ->firstOrFail();
    }

    private function recordTransition(
        StockReservation $reservation,
        ReservationStatus $from,
        ReservationStatus $to,
        ?User $actor,
        string $reason,
        ?string $operationReference = null,
    ): void {
        $this->history->record(
            $reservation->orderItem->order,
            OrderHistoryDomain::Reservation,
            $from->value,
            $to->value,
            $actor,
            $reason,
            $this->historyMetadata($reservation, $operationReference),
        );
    }

    /** @return array<string, int|string|null> */
    private function historyMetadata(StockReservation $reservation, ?string $operationReference = null): array
    {
        $reservation->loadMissing('orderItem');

        $metadata = [
            'reservation_id' => $reservation->getKey(),
            'order_item_id' => $reservation->order_item_id,
            'product_id' => $reservation->orderItem->product_id,
            'quantity' => $reservation->quantity,
        ];

        if ($operationReference !== null) {
            $metadata['operation_reference'] = $operationReference;
        }

        return $metadata;
    }

    private function operationReference(string $operation, Order $order): string
    {
        return "reservation:{$operation}:order:{$order->getKey()}";
    }
}
