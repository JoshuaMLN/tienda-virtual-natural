<?php

namespace App\Support\Delivery;

use App\Models\DeliveryDistrict;
use App\Support\Money\Money;
use App\Support\Settings\StorefrontSettings;

class DeliveryService
{
    public function __construct(
        private readonly StorefrontSettings $settings,
    ) {}

    public function coveredDistrict(string $ubigeo): ?DeliveryDistrict
    {
        return DeliveryDistrict::query()
            ->active()
            ->where('ubigeo', $ubigeo)
            ->first();
    }

    public function hasCoverage(string $ubigeo): bool
    {
        return $this->coveredDistrict($ubigeo) !== null;
    }

    public function quote(string $ubigeo, int|float|string $subtotal): ?DeliveryQuote
    {
        $district = $this->coveredDistrict($ubigeo);

        if ($district === null) {
            return null;
        }

        $subtotalCents = Money::fromDecimal($subtotal)->cents;
        $baseFeeCents = Money::fromDecimal($district->shipping_fee)->cents;
        $thresholdCents = $this->settings->freeShippingThresholdCents();
        $hasFreeShipping = $thresholdCents > 0 && $subtotalCents >= $thresholdCents;

        return new DeliveryQuote(
            ubigeo: $district->ubigeo,
            province: $district->province,
            district: $district->district,
            subtotalCents: $subtotalCents,
            baseFeeCents: $baseFeeCents,
            shippingFeeCents: $hasFreeShipping ? 0 : $baseFeeCents,
            hasFreeShipping: $hasFreeShipping,
        );
    }

    public function pickupAvailable(): bool
    {
        return $this->settings->pickupEnabled();
    }
}
