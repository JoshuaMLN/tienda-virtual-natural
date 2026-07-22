<?php

namespace App\Support\Orders;

use App\Enums\CustomerOrderStatus;
use App\Enums\DeliveryMethod;
use App\Enums\FiscalDocumentType;
use App\Enums\TaxAffectation;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Product;
use App\Support\Fiscal\FiscalIdentityDocumentMasker;
use App\Support\Money\Money;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class CustomerOrderDetailPresenter
{
    public function __construct(
        private readonly CustomerOrderDateFormatter $dates,
        private readonly CustomerOrderTimelineBuilder $timeline,
        private readonly FiscalIdentityDocumentMasker $identityMasker,
    ) {}

    /** @return array<string, mixed> */
    public function present(Order $order, CustomerOrderStatus $commercialStatus): array
    {
        $items = $order->items->map(fn (OrderItem $item): array => $this->item($item))->all();

        return [
            'created_at' => $this->dates->descriptive($order->created_at),
            'items' => $items,
            'product_count' => count($items),
            'total_quantity' => $order->items->sum('quantity'),
            'amounts' => $this->amounts($order),
            'contact' => [
                'name' => $order->customer_name,
                'email' => $order->customer_email,
                'phone' => $order->customer_phone,
            ],
            'delivery' => $this->delivery($order, $commercialStatus),
            'fiscal' => $this->fiscal($order),
            'timeline' => array_map(
                fn (CustomerOrderTimelineEvent $event): array => [
                    'event' => $event,
                    'formatted_date' => $this->dates->descriptive($event->occurredAt),
                ],
                $this->timeline->build($order),
            ),
        ];
    }

    /** @return array<string, mixed> */
    private function item(OrderItem $item): array
    {
        return [
            'name' => $item->product_name,
            'sku' => $item->product_sku,
            'presentation' => $item->product_presentation,
            'image_url' => $this->snapshotImageUrl($item->product_image),
            'product_url' => $item->product
                ? route('shop.product', $item->product->slug)
                : null,
            'quantity' => $item->quantity,
            'sale_unit' => $item->sale_unit,
            'formatted_unit_price' => Money::fromCents($item->unit_price_cents)->formatted(),
            'formatted_gross_total' => Money::fromCents($item->gross_total_cents)->formatted(),
            'formatted_discount' => Money::fromCents($item->discount_cents)->formatted(),
            'formatted_total' => Money::fromCents($item->total_cents)->formatted(),
            'has_discount' => $item->discount_cents > 0,
            'tax_label' => $this->taxLabel($item),
        ];
    }

    /** @return array<string, mixed> */
    private function amounts(Order $order): array
    {
        return [
            'products_subtotal' => Money::fromCents($order->products_subtotal_cents)->formatted(),
            'discount' => Money::fromCents($order->discount_cents)->formatted(),
            'has_discount' => $order->discount_cents > 0,
            'shipping' => $order->shipping_fee_cents === 0
                ? 'Gratis'
                : Money::fromCents($order->shipping_fee_cents)->formatted(),
            'taxable_value' => Money::fromCents($order->taxable_value_cents)->formatted(),
            'exempt_value' => Money::fromCents($order->exempt_value_cents)->formatted(),
            'unaffected_value' => Money::fromCents($order->unaffected_value_cents)->formatted(),
            'tax' => Money::fromCents($order->tax_cents)->formatted(),
            'total' => Money::fromCents($order->total_cents)->formatted(),
            'has_taxable_value' => $order->taxable_value_cents > 0,
            'has_exempt_value' => $order->exempt_value_cents > 0,
            'has_unaffected_value' => $order->unaffected_value_cents > 0,
        ];
    }

    /** @return array<string, mixed> */
    private function delivery(Order $order, CustomerOrderStatus $commercialStatus): array
    {
        $isPickup = $order->delivery_method === DeliveryMethod::Pickup;
        $reservationExpiration = null;

        if (in_array($commercialStatus, [
            CustomerOrderStatus::PendingPayment,
            CustomerOrderStatus::PaymentFailed,
        ], true) && $order->reservation_expires_at?->isFuture()) {
            $reservationExpiration = $this->dates->descriptive($order->reservation_expires_at);
        }

        $estimate = null;

        if ($order->paid_at !== null
            && $order->delivery_estimated_from !== null
            && $order->delivery_estimated_to !== null) {
            $estimate = $this->dates->dateRange(
                $order->delivery_estimated_from,
                $order->delivery_estimated_to,
            );
        }

        return [
            'is_pickup' => $isPickup,
            'method_label' => $order->delivery_method->label(),
            'icon' => $isPickup ? 'bi-shop' : 'bi-truck',
            'recipient_name' => $isPickup ? null : $order->delivery_recipient_name,
            'phone' => $isPickup ? null : $order->delivery_phone,
            'address' => $isPickup ? $order->pickup_address : $order->delivery_address,
            'location' => $isPickup
                ? null
                : implode(', ', array_filter([
                    $order->delivery_district,
                    $order->delivery_province,
                    $order->delivery_department,
                ])),
            'reference' => $isPickup ? null : $order->delivery_reference,
            'reservation_expires_at' => $reservationExpiration,
            'estimate' => $estimate,
            'estimate_label' => $isPickup ? 'Recojo disponible' : 'Entrega estimada',
        ];
    }

    /** @return array<string, mixed> */
    private function fiscal(Order $order): array
    {
        $isInvoice = $order->fiscal_document_type === FiscalDocumentType::Invoice;

        return [
            'type' => $order->fiscal_document_type->label(),
            'identity_type' => $order->fiscal_identity_document_type?->label(),
            'masked_identity' => $order->fiscal_identity_document_number
                ? $this->identityMasker->mask($order->fiscal_identity_document_number)
                : null,
            'holder' => $isInvoice
                ? $order->fiscal_business_name
                : trim($order->fiscal_first_names.' '.$order->fiscal_last_names),
            'address' => $isInvoice ? $order->fiscal_address : null,
            'email' => $order->fiscal_email,
        ];
    }

    private function snapshotImageUrl(?string $path): string
    {
        $path = trim((string) $path);

        if ($path === '') {
            return asset(Product::DEFAULT_IMAGE);
        }

        if (Str::startsWith($path, ['https://', 'http://'])) {
            return $path;
        }

        if (Str::startsWith(ltrim($path, '/'), 'images/')) {
            return asset(ltrim($path, '/'));
        }

        return Storage::disk('public')->exists($path)
            ? Storage::disk('public')->url($path)
            : asset(Product::DEFAULT_IMAGE);
    }

    private function taxLabel(OrderItem $item): string
    {
        if ($item->tax_affectation === TaxAffectation::Taxed) {
            return 'IGV incluido';
        }

        return $item->tax_affectation->label();
    }
}
