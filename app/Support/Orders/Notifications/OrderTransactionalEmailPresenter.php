<?php

namespace App\Support\Orders\Notifications;

use App\Enums\OrderNotificationType;
use App\Enums\PaymentStatus;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\OrderNotificationDelivery;
use App\Support\Money\Money;
use App\Support\Orders\CustomerOrderDateFormatter;
use App\Support\Orders\OrderCancellationDetailsResolver;

class OrderTransactionalEmailPresenter
{
    public const MAX_EMBEDDED_IMAGE_BYTES = 300_000;

    public function __construct(
        private readonly OrderEmailThumbnailService $thumbnails,
        private readonly CustomerOrderDateFormatter $dates,
        private readonly OrderCancellationDetailsResolver $cancellations,
    ) {}

    /** @return array<string, mixed> */
    public function present(OrderNotificationDelivery $delivery): array
    {
        $order = $delivery->order;
        $event = $this->event($delivery->type, $order);
        $cancellation = $delivery->type === OrderNotificationType::Cancelled
            ? $this->cancellations->resolve($order)
            : null;
        $items = [];
        $embeddedImages = [];
        $embeddedBytes = 0;
        $fallbackThumbnail = null;

        if ($delivery->type === OrderNotificationType::Created) {
            foreach ($order->items->sortBy('id') as $item) {
                $thumbnail = $this->thumbnails->make($item->product_image);

                if (! isset($embeddedImages[$thumbnail->fingerprint])
                    && $embeddedBytes + $thumbnail->bytes()
                        > self::MAX_EMBEDDED_IMAGE_BYTES - OrderEmailThumbnailService::TARGET_MAX_BYTES) {
                    $fallbackThumbnail ??= $this->thumbnails->make(null);
                    $thumbnail = $fallbackThumbnail;
                }

                $embeddedImages[$thumbnail->fingerprint] ??= [
                    'key' => $thumbnail->fingerprint,
                    'contents' => $thumbnail->contents,
                    'filename' => $thumbnail->filename,
                    'mime' => 'image/jpeg',
                    'bytes' => $thumbnail->bytes(),
                ];
                $embeddedBytes = array_sum(array_column($embeddedImages, 'bytes'));
                $items[] = $this->item($item, $thumbnail->fingerprint);
            }
        }

        return [
            'brand' => (string) config('app.name'),
            'subject' => $event['subject'].' | '.config('app.name'),
            'preheader' => $event['preheader'],
            'heading' => $event['heading'],
            'summary' => $event['summary'],
            'status_label' => $event['status_label'],
            'status_tone' => $event['status_tone'],
            'notice' => $event['notice'],
            'recipient_name' => $delivery->recipient_name ?: $order->customer_name,
            'order_code' => $order->code,
            'items' => $items,
            'embedded_images' => array_values($embeddedImages),
            'embedded_image_bytes' => $embeddedBytes,
            'has_items' => $items !== [],
            'products_subtotal' => Money::fromCents($order->products_subtotal_cents)->formatted(),
            'has_discount' => $order->discount_cents > 0,
            'discount' => Money::fromCents($order->discount_cents)->formatted(),
            'shipping' => $order->shipping_fee_cents > 0
                ? Money::fromCents($order->shipping_fee_cents)->formatted()
                : 'Gratis',
            'total' => Money::fromCents($order->total_cents)->formatted(),
            'delivery_method' => $order->delivery_method->label(),
            'reservation_expiration' => $delivery->type === OrderNotificationType::Created
                && $order->reservation_expires_at !== null
                    ? $this->dates->descriptive($order->reservation_expires_at)
                    : null,
            'cancellation' => $cancellation === null
                ? null
                : [
                    'title' => $cancellation->title,
                    'reason' => $cancellation->reason,
                    'refund_message' => $cancellation->refundMessage,
                ],
            'action_label' => $event['action_label'],
            'action_url' => $order->user_id !== null
                ? route('account.orders.show', $order->code)
                : null,
            'year' => (int) now()->format('Y'),
        ];
    }

    /** @return array<string, mixed> */
    private function item(OrderItem $item, string $imageKey): array
    {
        return [
            'name' => $item->product_name,
            'presentation' => $item->product_presentation,
            'quantity' => $item->quantity,
            'has_multiple_units' => $item->quantity > 1,
            'unit_price' => Money::fromCents($item->unit_price_cents)->formatted(),
            'line_subtotal' => Money::fromCents($item->gross_total_cents)->formatted(),
            'image_key' => $imageKey,
        ];
    }

    /** @return array<string, string> */
    private function event(OrderNotificationType $type, Order $order): array
    {
        return match ($type) {
            OrderNotificationType::Created => [
                'subject' => "Recibimos tu pedido {$order->code}",
                'preheader' => "Tu pedido {$order->code} esta pendiente de pago.",
                'heading' => 'Pedido recibido',
                'summary' => "Tu pedido {$order->code} fue creado y esta pendiente de pago.",
                'status_label' => 'Pendiente de pago',
                'status_tone' => 'pending',
                'notice' => 'Conservamos el stock durante el plazo indicado mientras completas el pago.',
                'action_label' => 'Continuar con el pago',
            ],
            OrderNotificationType::Cancelled => [
                'subject' => "Pedido {$order->code} cancelado",
                'preheader' => "El pedido {$order->code} fue cancelado.",
                'heading' => 'Pedido cancelado',
                'summary' => "Tu pedido {$order->code} fue cancelado.",
                'status_label' => 'Cancelado',
                'status_tone' => 'cancelled',
                'notice' => in_array($order->payment_status, [
                    PaymentStatus::RefundPending,
                    PaymentStatus::Refunded,
                ], true)
                    ? 'El pedido ya no continuara con la entrega.'
                    : 'La reserva de stock fue liberada y el pedido ya no puede pagarse.',
                'action_label' => 'Ver pedido',
            ],
            OrderNotificationType::Expired => [
                'subject' => "La reserva de tu pedido {$order->code} vencio",
                'preheader' => "La reserva del pedido {$order->code} vencio.",
                'heading' => 'Reserva vencida',
                'summary' => "La reserva de stock del pedido {$order->code} vencio antes de completar el pago.",
                'status_label' => 'Vencido',
                'status_tone' => 'expired',
                'notice' => 'Los productos reservados fueron liberados y este pedido ya no puede pagarse.',
                'action_label' => 'Ver pedido',
            ],
            OrderNotificationType::Shipped => [
                'subject' => "Tu pedido {$order->code} esta en camino",
                'preheader' => "El pedido {$order->code} ya fue despachado.",
                'heading' => 'Pedido en camino',
                'summary' => "Tu pedido {$order->code} ya fue despachado y esta en camino.",
                'status_label' => 'En camino',
                'status_tone' => 'active',
                'notice' => 'Te avisaremos cuando la entrega haya sido confirmada.',
                'action_label' => 'Ver pedido',
            ],
            OrderNotificationType::PickupReady => [
                'subject' => "Tu pedido {$order->code} esta listo para recoger",
                'preheader' => "El pedido {$order->code} ya esta disponible para recojo.",
                'heading' => 'Pedido listo para recoger',
                'summary' => "Tu pedido {$order->code} ya esta disponible para recoger.",
                'status_label' => 'Listo para recoger',
                'status_tone' => 'active',
                'notice' => $this->pickupDeadlineNotice($order),
                'action_label' => 'Ver pedido',
            ],
            OrderNotificationType::Delivered => [
                'subject' => "Tu pedido {$order->code} fue entregado",
                'preheader' => "Confirmamos la entrega del pedido {$order->code}.",
                'heading' => 'Pedido entregado',
                'summary' => "Confirmamos que tu pedido {$order->code} fue entregado.",
                'status_label' => 'Entregado',
                'status_tone' => 'active',
                'notice' => 'Gracias por confiar en nosotros.',
                'action_label' => 'Ver pedido',
            ],
            OrderNotificationType::PickedUp => [
                'subject' => "Tu pedido {$order->code} fue recogido",
                'preheader' => "Confirmamos el recojo del pedido {$order->code}.",
                'heading' => 'Pedido recogido',
                'summary' => "Confirmamos que tu pedido {$order->code} fue recogido.",
                'status_label' => 'Recogido',
                'status_tone' => 'active',
                'notice' => 'Gracias por confiar en nosotros.',
                'action_label' => 'Ver pedido',
            ],
            OrderNotificationType::PickupMidpointReminder => [
                'subject' => "Recuerda recoger tu pedido {$order->code}",
                'preheader' => "Tu pedido {$order->code} sigue disponible para recojo.",
                'heading' => 'Tu pedido sigue esperandote',
                'summary' => "Tu pedido {$order->code} sigue disponible para recoger.",
                'status_label' => 'Recojo pendiente',
                'status_tone' => 'active',
                'notice' => $this->pickupDeadlineNotice($order),
                'action_label' => 'Ver pedido',
            ],
            OrderNotificationType::Pickup48HoursReminder => [
                'subject' => "Tu pedido {$order->code} vence pronto",
                'preheader' => "Quedan 48 horas para recoger el pedido {$order->code}.",
                'heading' => 'Tu plazo de recojo vence pronto',
                'summary' => "Quedan 48 horas para recoger tu pedido {$order->code}.",
                'status_label' => 'Recojo pendiente',
                'status_tone' => 'active',
                'notice' => $this->pickupDeadlineNotice($order),
                'action_label' => 'Ver pedido',
            ],
            OrderNotificationType::PickupDeadlineReminder => [
                'subject' => "El plazo de recojo de tu pedido {$order->code} vencio",
                'preheader' => "El pedido {$order->code} requiere coordinacion para su recojo.",
                'heading' => 'Tu plazo de recojo vencio',
                'summary' => "El plazo para recoger tu pedido {$order->code} vencio.",
                'status_label' => 'Coordinacion necesaria',
                'status_tone' => 'expired',
                'notice' => 'Comunicate con nosotros para coordinar el recojo. Tu pedido no fue cancelado.',
                'action_label' => 'Ver pedido',
            ],
        };
    }

    private function pickupDeadlineNotice(Order $order): string
    {
        return $order->pickup_deadline_at === null
            ? 'Te esperamos en el punto de recojo registrado en tu pedido.'
            : 'Puedes recogerlo hasta el '.$this->dates->descriptive($order->pickup_deadline_at).'.';
    }
}
