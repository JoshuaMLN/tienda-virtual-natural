<?php

namespace App\Support\Orders\Pricing;

use App\Enums\TaxAffectation;
use App\Models\Product;

final readonly class OrderLinePricing
{
    public function __construct(
        public Product $product,
        public int $quantity,
        public TaxAffectation $taxAffectation,
        public int $taxRateBasisPoints,
        public int $unitPriceCents,
        public int $grossTotalCents,
        public int $discountCents,
        public int $netValueCents,
        public int $taxCents,
        public int $totalCents,
    ) {}

    /** @return array<string, mixed> */
    public function snapshotAttributes(): array
    {
        return [
            'product_id' => $this->product->getKey(),
            'product_sku' => $this->product->sku,
            'product_name' => $this->product->name,
            'product_image' => $this->product->primaryImage?->image_path ?? Product::DEFAULT_IMAGE,
            'product_presentation' => $this->product->short_description,
            'sale_unit' => 'unidad',
            'quantity' => $this->quantity,
            'tax_affectation' => $this->taxAffectation,
            'tax_rate_bps' => $this->taxRateBasisPoints,
            'unit_price_cents' => $this->unitPriceCents,
            'gross_total_cents' => $this->grossTotalCents,
            'discount_cents' => $this->discountCents,
            'net_value_cents' => $this->netValueCents,
            'tax_cents' => $this->taxCents,
            'total_cents' => $this->totalCents,
        ];
    }
}
