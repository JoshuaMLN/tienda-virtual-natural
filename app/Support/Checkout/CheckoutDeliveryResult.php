<?php

namespace App\Support\Checkout;

use App\Enums\DeliveryMethod;
use App\Support\Delivery\DeliveryQuote;
use App\Support\Delivery\EstimatedDateRange;
use App\Support\Money\Money;

final readonly class CheckoutDeliveryResult
{
    public function __construct(
        public DeliveryMethod $method,
        public ?int $addressId,
        public ?DeliveryQuote $deliveryQuote,
        public CheckoutSummary $summary,
        public int $deliveryBusinessDaysMin,
        public int $deliveryBusinessDaysMax,
        public EstimatedDateRange $estimatedDates,
        public ?string $pickupAddress,
        public int $pickupHoldDays,
    ) {}

    public function shippingFeeCents(): int
    {
        return $this->summary->pricing->shippingFeeCents;
    }

    public function snapshot(): CheckoutDeliverySnapshot
    {
        return new CheckoutDeliverySnapshot(
            method: $this->method,
            addressId: $this->addressId,
            ubigeo: $this->deliveryQuote?->ubigeo,
            baseFeeCents: $this->deliveryQuote?->baseFeeCents ?? 0,
            hasFreeShipping: $this->deliveryQuote?->hasFreeShipping ?? false,
            deliveryBusinessDaysMin: $this->deliveryBusinessDaysMin,
            deliveryBusinessDaysMax: $this->deliveryBusinessDaysMax,
            estimatedFrom: $this->estimatedDates->from->toDateString(),
            estimatedTo: $this->estimatedDates->to->toDateString(),
            pickupAddress: $this->pickupAddress,
            pickupHoldDays: $this->method === DeliveryMethod::Pickup ? $this->pickupHoldDays : null,
            items: $this->summary->itemSnapshots(),
            amounts: $this->summary->pricing->orderAmountAttributes(),
        );
    }

    /** @return array<string, mixed> */
    public function toArray(): array
    {
        $baseFeeCents = $this->deliveryQuote?->baseFeeCents ?? 0;
        $shippingFeeCents = $this->shippingFeeCents();
        $isPickup = $this->method === DeliveryMethod::Pickup;
        $isFreeByDistrict = ! $isPickup && $baseFeeCents === 0;

        return [
            'available' => true,
            'method' => $this->method->value,
            'method_label' => $this->method->label(),
            'address_id' => $this->addressId,
            'ubigeo' => $this->deliveryQuote?->ubigeo,
            'province' => $this->deliveryQuote?->province,
            'district' => $this->deliveryQuote?->district,
            'base_fee_cents' => $baseFeeCents,
            'shipping_fee_cents' => $shippingFeeCents,
            'formatted_base_fee' => Money::fromCents($baseFeeCents)->formatted(),
            'formatted_shipping_fee' => Money::fromCents($shippingFeeCents)->formatted(),
            'has_free_shipping' => $this->deliveryQuote?->hasFreeShipping ?? false,
            'is_free_by_district' => $isFreeByDistrict,
            'is_pickup' => $isPickup,
            'delivery_window_label' => $this->estimatedDates->label(),
            'pickup_availability_label' => $isPickup ? $this->estimatedDates->availabilityLabel() : null,
            ...$this->estimatedDates->toArray(),
            'pickup_address' => $this->pickupAddress,
            'pickup_hold_days' => $this->pickupHoldDays,
            'message' => $this->message($isFreeByDistrict),
            'quote_reference' => $this->snapshot()->fingerprint(),
            'summary' => [
                'amounts' => $this->summary->amountsToArray(),
            ],
        ];
    }

    private function message(bool $isFreeByDistrict): string
    {
        if ($this->method === DeliveryMethod::Pickup) {
            return "Recojo sin costo. Tu pedido estara disponible para recojo {$this->estimatedDates->availabilityLabel()}. Te avisaremos apenas este listo.";
        }

        $district = $this->deliveryQuote?->district ?? 'el distrito seleccionado';
        $estimatedDelivery = "Entrega estimada: {$this->estimatedDates->label()}.";

        if ($isFreeByDistrict) {
            return "Entrega gratuita a {$district}. {$estimatedDelivery}";
        }

        if ($this->deliveryQuote?->hasFreeShipping) {
            return "Envio gratis a {$district} por alcanzar el monto requerido. {$estimatedDelivery}";
        }

        return "Entrega a {$district}: ".Money::fromCents($this->shippingFeeCents())->formatted().". {$estimatedDelivery}";
    }
}
