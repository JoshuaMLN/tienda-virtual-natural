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

    public function coveredDistrict(string $ubigeo, bool $lockForUpdate = false): ?DeliveryDistrict
    {
        $query = DeliveryDistrict::query()
            ->active()
            ->where('ubigeo', $ubigeo);

        if ($lockForUpdate) {
            $query->lockForUpdate();
        }

        return $query->first();
    }

    public function hasCoverage(string $ubigeo): bool
    {
        return $this->coveredDistrict($ubigeo) !== null;
    }

    public function quote(string $ubigeo, int|float|string $subtotal): ?DeliveryQuote
    {
        return $this->quoteCents(
            $ubigeo,
            Money::fromDecimal($subtotal)->cents,
        );
    }

    public function quoteCents(
        string $ubigeo,
        int $subtotalCents,
        bool $lockForUpdate = false,
    ): ?DeliveryQuote {
        $district = $this->coveredDistrict($ubigeo, $lockForUpdate);

        if ($district === null) {
            return null;
        }

        $subtotalCents = max(0, $subtotalCents);
        $baseFeeCents = Money::fromDecimal($district->shipping_fee)->cents;
        $thresholdCents = $this->settings->freeShippingThresholdCents();
        $hasFreeShipping = $thresholdCents > 0 && $subtotalCents >= $thresholdCents;
        [$businessDaysMin, $businessDaysMax] = $district->deliveryWindow(
            $this->settings->deliveryBusinessDaysMin(),
            $this->settings->deliveryBusinessDaysMax(),
        );

        return new DeliveryQuote(
            ubigeo: $district->ubigeo,
            province: $district->province,
            district: $district->district,
            subtotalCents: $subtotalCents,
            baseFeeCents: $baseFeeCents,
            shippingFeeCents: $hasFreeShipping ? 0 : $baseFeeCents,
            hasFreeShipping: $hasFreeShipping,
            businessDaysMin: $businessDaysMin,
            businessDaysMax: $businessDaysMax,
        );
    }

    public function pickupAvailable(): bool
    {
        return $this->settings->pickupEnabled();
    }
}
