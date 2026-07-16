<?php

namespace App\Support\Delivery;

final readonly class DeliveryQuote
{
    public function __construct(
        public string $ubigeo,
        public string $province,
        public string $district,
        public int $subtotalCents,
        public int $baseFeeCents,
        public int $shippingFeeCents,
        public bool $hasFreeShipping,
    ) {}
}
