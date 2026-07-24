<?php

namespace App\Support\Orders;

use App\Enums\DeliveryStatus;
use App\Enums\OrderHistoryDomain;
use App\Enums\OrderStatus;
use App\Enums\PaymentStatus;
use App\Enums\ReservationStatus;
use App\Models\FiscalDocument;
use App\Models\FiscalDocumentDelivery;
use App\Models\Order;
use App\Models\OrderNotificationDelivery;
use App\Models\OrderStatusHistory;
use App\Models\StockReservation;
use App\Support\Money\Money;
use DateTimeInterface;
use Illuminate\Support\Collection;

class AdminOrderPresenter
{
    public function __construct(
        private readonly CustomerOrderStatusResolver $statuses,
        private readonly CustomerOrderDateFormatter $dates,
        private readonly CustomerOrderDetailPresenter $customerDetails,
    ) {}

    /** @return array<string, mixed> */
    public function listItem(Order $order): array
    {
        return [
            'order' => $order,
            'commercial_status' => $this->statuses->resolve($order),
            'technical_statuses' => $this->technicalStatuses($order),
            'formatted_total' => Money::fromCents($order->total_cents)->formatted(),
            'formatted_date' => $this->dates->compactDate($order->created_at),
            'formatted_time' => $this->dates->compactTime($order->created_at),
            'product_count' => (int) ($order->items_count ?? 0),
            'total_quantity' => (int) ($order->total_quantity ?? 0),
        ];
    }

    /** @return array<string, mixed> */
    public function detail(Order $order): array
    {
        $commercialStatus = $this->statuses->resolve($order);
        $base = $this->customerDetails->present($order, $commercialStatus);
        $reservations = $order->stockReservations
            ->map(fn (StockReservation $reservation): array => $this->reservation($reservation));

        return array_merge($base, [
            'commercial_status' => $commercialStatus,
            'technical_statuses' => $this->technicalStatuses($order),
            'history_domains' => $this->historyDomains(),
            'account' => [
                'exists' => $order->user !== null,
                'name' => $order->user?->name,
                'email' => $order->user?->email,
                'verified' => $order->user?->email_verified_at !== null,
            ],
            'fiscal' => array_merge($base['fiscal'], [
                'identity' => $order->fiscal_identity_document_number,
            ]),
            'reservation_summary' => $this->reservationSummary($reservations),
            'reservations' => $reservations->all(),
            'history' => $this->groupedHistory($order),
            'documents' => $order->fiscalDocuments
                ->map(fn (FiscalDocument $document): array => $this->document($document))
                ->all(),
            'communications' => $this->communications($order),
            'terms' => [
                'version' => $order->terms_document_version,
                'accepted_at' => $this->formatDate($order->terms_accepted_at),
                'fingerprint' => $order->terms_content_fingerprint,
            ],
        ]);
    }

    /** @return array<string, array{label: string, value: string, explanation: ?string}> */
    private function technicalStatuses(Order $order): array
    {
        $payment = [
            'label' => 'Pago',
            'value' => $order->payment_status->label(),
            'explanation' => null,
        ];
        $delivery = [
            'label' => 'Entrega',
            'value' => $order->delivery_status->label(),
            'explanation' => null,
        ];

        if ($order->order_status === OrderStatus::Cancelled
            && in_array($order->payment_status, [PaymentStatus::Pending, PaymentStatus::Failed], true)) {
            $payment['value'] = 'No realizado';
            $payment['explanation'] = 'El pedido se cancelo antes de completar el pago.';
        }

        if (in_array($order->order_status, [OrderStatus::Cancelled, OrderStatus::Expired], true)
            && $order->delivery_status === DeliveryStatus::Pending) {
            $delivery['value'] = 'No aplica';
            $delivery['explanation'] = $order->order_status === OrderStatus::Expired
                ? 'El pedido vencio antes de iniciar la entrega.'
                : 'El pedido se cancelo antes de iniciar la entrega.';
        }

        return [
            'order' => [
                'label' => 'Pedido',
                'value' => $order->order_status->label(),
                'explanation' => null,
            ],
            'payment' => $payment,
            'delivery' => $delivery,
        ];
    }

    /** @return array<string, mixed> */
    private function reservation(StockReservation $reservation): array
    {
        $closedAt = match ($reservation->status) {
            ReservationStatus::Consumed => $reservation->consumed_at,
            ReservationStatus::Released => $reservation->released_at,
            ReservationStatus::Expired => $reservation->expired_at,
            ReservationStatus::Active => null,
        };

        return [
            'product' => $reservation->orderItem?->product_name ?? 'Producto historico',
            'sku' => $reservation->orderItem?->product_sku,
            'quantity' => $reservation->quantity,
            'status' => $reservation->status->label(),
            'expires_at' => $this->formatDate($reservation->expires_at),
            'closed_at' => $this->formatDate($closedAt),
            'release_reason' => $reservation->release_reason,
        ];
    }

    /** @param Collection<int, array<string, mixed>> $reservations */
    private function reservationSummary(Collection $reservations): ?array
    {
        if ($reservations->isEmpty()) {
            return null;
        }

        $statusCounts = $reservations->countBy('status');
        $isMixed = $statusCounts->count() > 1;
        $status = $isMixed ? 'Estado mixto' : (string) $statusCounts->keys()->first();

        return [
            'product_count' => $reservations->count(),
            'total_quantity' => $reservations->sum('quantity'),
            'status' => $status,
            'is_mixed' => $isMixed,
            'description' => $isMixed
                ? 'Las reservas no comparten el mismo estado. Revisa el detalle por producto.'
                : $this->reservationStatusDescription($status),
            'breakdown' => $statusCounts
                ->map(fn (int $count, string $label): array => [
                    'label' => $label,
                    'count' => $count,
                ])
                ->values()
                ->all(),
        ];
    }

    private function reservationStatusDescription(string $status): string
    {
        return match ($status) {
            ReservationStatus::Active->label() => 'El stock permanece separado mientras se completa el pago.',
            ReservationStatus::Consumed->label() => 'El stock se desconto definitivamente al confirmar el pago.',
            ReservationStatus::Released->label() => 'El stock reservado fue devuelto al inventario.',
            ReservationStatus::Expired->label() => 'La reserva vencio y el stock regreso al inventario.',
            default => 'Consulta el detalle de las reservas del pedido.',
        };
    }

    /** @return list<array<string, mixed>> */
    private function groupedHistory(Order $order): array
    {
        $entries = [];
        $reservationGroup = [];
        $itemsById = $order->items->keyBy('id');

        foreach ($order->statusHistories as $history) {
            if ($history->domain === OrderHistoryDomain::Reservation) {
                $previous = $reservationGroup === [] ? null : end($reservationGroup);

                if ($previous !== null && ! $this->sameReservationOperation($previous, $history)) {
                    $entries[] = $this->reservationHistoryGroup($reservationGroup, $itemsById);
                    $reservationGroup = [];
                }

                $reservationGroup[] = $history;

                continue;
            }

            if ($reservationGroup !== []) {
                $entries[] = $this->reservationHistoryGroup($reservationGroup, $itemsById);
                $reservationGroup = [];
            }

            $entries[] = $this->history($history);
        }

        if ($reservationGroup !== []) {
            $entries[] = $this->reservationHistoryGroup($reservationGroup, $itemsById);
        }

        return $entries;
    }

    private function sameReservationOperation(
        OrderStatusHistory $previous,
        OrderStatusHistory $current,
    ): bool {
        $previousReference = data_get($previous->metadata, 'operation_reference');
        $currentReference = data_get($current->metadata, 'operation_reference');

        if (is_string($previousReference) || is_string($currentReference)) {
            return is_string($previousReference)
                && is_string($currentReference)
                && hash_equals($previousReference, $currentReference)
                && $previous->from_status === $current->from_status
                && $previous->to_status === $current->to_status;
        }

        return $previous->from_status === $current->from_status
            && $previous->to_status === $current->to_status
            && $previous->actor_id === $current->actor_id
            && $previous->actor_name === $current->actor_name
            && $previous->actor_email === $current->actor_email
            && $previous->reason === $current->reason
            && $previous->created_at->diffInSeconds($current->created_at) <= 60;
    }

    /**
     * @param  list<OrderStatusHistory>  $histories
     * @param  Collection<int, mixed>  $itemsById
     * @return array<string, mixed>
     */
    private function reservationHistoryGroup(array $histories, Collection $itemsById): array
    {
        $entry = $this->history($histories[0]);
        $items = collect($histories)
            ->map(function (OrderStatusHistory $history) use ($itemsById): ?array {
                $orderItemId = data_get($history->metadata, 'order_item_id');

                if (! is_int($orderItemId)
                    && (! is_string($orderItemId) || ! ctype_digit($orderItemId))) {
                    return null;
                }

                $item = $itemsById->get((int) $orderItemId);

                return [
                    'reservation_id' => data_get($history->metadata, 'reservation_id'),
                    'product' => $item?->product_name ?? 'Producto historico',
                    'sku' => $item?->product_sku,
                    'quantity' => (int) (data_get($history->metadata, 'quantity') ?? $item?->quantity ?? 0),
                ];
            })
            ->filter()
            ->unique('reservation_id')
            ->values();

        $metadata = collect($histories)
            ->pluck('metadata')
            ->filter()
            ->values()
            ->all();

        $entry['reservation_items'] = $items->all();
        $entry['reservation_count'] = max($items->count(), count($histories));
        $entry['reservation_summary'] = $this->reservationOperationSummary(
            ReservationStatus::tryFrom($histories[0]->to_status),
            $entry['reservation_count'],
            $items->sum('quantity'),
        );
        $entry['metadata_json'] = $metadata === []
            ? null
            : json_encode(
                count($metadata) === 1 ? $metadata[0] : $metadata,
                JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE,
            );

        return $entry;
    }

    private function reservationOperationSummary(
        ?ReservationStatus $status,
        int $products,
        int $quantity,
    ): string {
        $productLabel = $products === 1 ? '1 producto' : "{$products} productos";
        $quantityLabel = $quantity === 1 ? '1 unidad' : "{$quantity} unidades";
        $quantitySummary = $quantity > 0 ? " ({$quantityLabel})" : '';

        return match ($status) {
            ReservationStatus::Active => $products === 1
                ? "Se reservo {$productLabel}{$quantitySummary}."
                : "Se reservaron {$productLabel}{$quantitySummary}.",
            ReservationStatus::Consumed => $products === 1
                ? "Se consumio 1 reserva{$quantitySummary}."
                : "Se consumieron {$products} reservas{$quantitySummary}.",
            ReservationStatus::Released => $products === 1
                ? "Se libero 1 reserva{$quantitySummary}."
                : "Se liberaron {$products} reservas{$quantitySummary}.",
            ReservationStatus::Expired => $products === 1
                ? "Vencio 1 reserva{$quantitySummary}."
                : "Vencieron {$products} reservas{$quantitySummary}.",
            default => "Se actualizaron las reservas de {$productLabel}{$quantitySummary}.",
        };
    }

    /** @return array<string, mixed> */
    private function history(OrderStatusHistory $history): array
    {
        $domain = $this->historyDomain($history->domain);

        return [
            'domain' => $domain['label'],
            'domain_key' => $domain['key'],
            'domain_icon' => $domain['icon'],
            'from' => $this->historyStatusLabel($history->domain, $history->from_status),
            'to' => $this->historyStatusLabel($history->domain, $history->to_status),
            'actor' => $history->actor_name ?: 'Sistema',
            'actor_email' => $history->actor_email,
            'reason' => $history->reason,
            'metadata' => $history->metadata,
            'metadata_json' => $history->metadata
                ? json_encode($history->metadata, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE)
                : null,
            'reservation_items' => [],
            'reservation_count' => 0,
            'reservation_summary' => null,
            'occurred_at' => $this->dates->descriptive($history->created_at),
        ];
    }

    /** @return list<array{key: string, label: string, icon: string}> */
    private function historyDomains(): array
    {
        return array_map(
            fn (OrderHistoryDomain $domain): array => $this->historyDomain($domain),
            OrderHistoryDomain::cases(),
        );
    }

    /** @return array{key: string, label: string, icon: string} */
    private function historyDomain(OrderHistoryDomain $domain): array
    {
        return [
            'key' => $domain->value,
            'label' => $domain->label(),
            'icon' => match ($domain) {
                OrderHistoryDomain::Order => 'bi-bag-check',
                OrderHistoryDomain::Payment => 'bi-credit-card',
                OrderHistoryDomain::Delivery => 'bi-truck',
                OrderHistoryDomain::Reservation => 'bi-box-seam',
            },
        ];
    }

    private function historyStatusLabel(OrderHistoryDomain $domain, ?string $status): string
    {
        if ($status === null) {
            return 'Inicio';
        }

        return match ($domain) {
            OrderHistoryDomain::Order => OrderStatus::tryFrom($status)?->label() ?? $status,
            OrderHistoryDomain::Payment => PaymentStatus::tryFrom($status)?->label() ?? $status,
            OrderHistoryDomain::Delivery => DeliveryStatus::tryFrom($status)?->label() ?? $status,
            OrderHistoryDomain::Reservation => ReservationStatus::tryFrom($status)?->label() ?? $status,
        };
    }

    /** @return array<string, mixed> */
    private function document(FiscalDocument $document): array
    {
        return [
            'type' => $document->type->label(),
            'reference' => $document->series.'-'.$document->correlative,
            'status' => $document->status->label(),
            'issued_at' => $this->formatDate($document->issued_at),
            'has_pdf' => trim((string) $document->pdf_path) !== '',
            'has_xml' => trim((string) $document->xml_path) !== '',
            'registrar' => $document->registrar_name ?: 'Sistema',
            'parent_reference' => $document->parentDocument
                ? $document->parentDocument->series.'-'.$document->parentDocument->correlative
                : null,
            'annulment_reason' => $document->annulment_reason,
        ];
    }

    /** @return list<array<string, mixed>> */
    private function communications(Order $order): array
    {
        $notifications = $order->notificationDeliveries
            ->map(fn (OrderNotificationDelivery $delivery): array => [
                'kind' => 'Pedido',
                'event' => $delivery->type->label(),
                'recipient' => $delivery->recipient_email,
                'status' => $delivery->status->label(),
                'attempts' => $delivery->attempts,
                'actor' => null,
                'error' => $delivery->last_error,
                'sort_at' => $delivery->sent_at
                    ?? $delivery->last_attempt_at
                    ?? $delivery->queued_at
                    ?? $delivery->created_at,
            ]);

        $fiscalDeliveries = $order->fiscalDocuments
            ->flatMap(fn (FiscalDocument $document): Collection => $document->deliveries
                ->map(fn (FiscalDocumentDelivery $delivery): array => [
                    'kind' => 'Fiscal',
                    'event' => $document->type->label().' '.$document->series.'-'.$document->correlative,
                    'recipient' => $delivery->recipient_email,
                    'status' => $delivery->status->label(),
                    'attempts' => 1,
                    'actor' => $delivery->attempted_by_name,
                    'error' => $delivery->error_message,
                    'sort_at' => $delivery->attempted_at ?? $delivery->created_at,
                ]));

        return $notifications
            ->concat($fiscalDeliveries)
            ->sortByDesc(fn (array $delivery): int => $delivery['sort_at']?->getTimestamp() ?? 0)
            ->map(function (array $delivery): array {
                $delivery['occurred_at'] = $this->formatDate($delivery['sort_at']);
                unset($delivery['sort_at']);

                return $delivery;
            })
            ->values()
            ->all();
    }

    private function formatDate(?DateTimeInterface $date): ?string
    {
        return $date ? $this->dates->descriptive($date) : null;
    }
}
