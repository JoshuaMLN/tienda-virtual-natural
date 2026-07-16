<?php

namespace App\Support\Orders\Pricing;

final readonly class OrderPricing
{
    /** @param list<OrderLinePricing> $lines */
    public function __construct(
        public array $lines,
        public int $productsSubtotalCents,
        public int $discountCents,
        public int $shippingFeeCents,
        public int $shippingNetValueCents,
        public int $shippingTaxCents,
        public int $taxableValueCents,
        public int $exemptValueCents,
        public int $unaffectedValueCents,
        public int $netValueCents,
        public int $taxCents,
        public int $totalCents,
    ) {}

    /** @return array<string, int> */
    public function orderAmountAttributes(): array
    {
        return [
            'products_subtotal_cents' => $this->productsSubtotalCents,
            'discount_cents' => $this->discountCents,
            'shipping_fee_cents' => $this->shippingFeeCents,
            'shipping_net_value_cents' => $this->shippingNetValueCents,
            'shipping_tax_cents' => $this->shippingTaxCents,
            'taxable_value_cents' => $this->taxableValueCents,
            'exempt_value_cents' => $this->exemptValueCents,
            'unaffected_value_cents' => $this->unaffectedValueCents,
            'net_value_cents' => $this->netValueCents,
            'tax_cents' => $this->taxCents,
            'total_cents' => $this->totalCents,
        ];
    }
}
