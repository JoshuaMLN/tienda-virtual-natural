<?php

namespace App\Support\Orders\Notifications;

use App\Enums\OrderNotificationType;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\OrderNotificationDelivery;
use App\Support\Money\Money;
use App\Support\Orders\CustomerOrderDateFormatter;

class OrderTransactionalEmailPresenter
{
    public const MAX_EMBEDDED_IMAGE_BYTES = 300_000;

    public function __construct(
        private readonly OrderEmailThumbnailService $thumbnails,
        private readonly CustomerOrderDateFormatter $dates,
    ) {}

    /** @return array<string, mixed> */
    public function present(OrderNotificationDelivery $delivery): array
    {
        $order = $delivery->order;
        $event = $this->event($delivery->type, $order);
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
                'notice' => 'La reserva de stock fue liberada y el pedido ya no puede pagarse.',
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
        };
    }
}
