<?php

namespace App\Support\Checkout;

use App\Support\Cart\Cart;
use App\Support\Money\Money;
use App\Support\Orders\Pricing\OrderLinePricing;
use App\Support\Orders\Pricing\OrderPricing;

final readonly class CheckoutSummary
{
    public function __construct(
        public Cart $cart,
        public OrderPricing $pricing,
    ) {}

    /** @return array<string, mixed> */
    public function toArray(): array
    {
        return [
            'items' => array_map(
                fn (OrderLinePricing $line): array => $this->lineToArray($line),
                $this->pricing->lines,
            ),
            'product_count' => $this->cart->productCount(),
            'total_quantity' => $this->cart->totalQuantity(),
            'warnings' => $this->cart->warnings,
            'amounts' => $this->amountsToArray(),
        ];
    }

    /** @return array<string, int|string> */
    public function amountsToArray(): array
    {
        return [
            ...$this->pricing->orderAmountAttributes(),
            'formatted_products_subtotal' => $this->format($this->pricing->productsSubtotalCents),
            'formatted_taxable_value' => $this->format($this->pricing->taxableValueCents),
            'formatted_exempt_value' => $this->format($this->pricing->exemptValueCents),
            'formatted_unaffected_value' => $this->format($this->pricing->unaffectedValueCents),
            'formatted_net_value' => $this->format($this->pricing->netValueCents),
            'formatted_tax' => $this->format($this->pricing->taxCents),
            'formatted_total' => $this->format($this->pricing->totalCents),
        ];
    }

    /**
     * @return list<array{
     *     product_id: int,
     *     product_sku: string,
     *     product_name: string,
     *     quantity: int,
     *     tax_affectation: string,
     *     tax_rate_bps: int,
     *     unit_price_cents: int,
     *     gross_total_cents: int,
     *     discount_cents: int,
     *     net_value_cents: int,
     *     tax_cents: int,
     *     total_cents: int
     * }>
     */
    public function itemSnapshots(): array
    {
        $items = array_map(
            fn (OrderLinePricing $line): array => [
                'product_id' => (int) $line->product->getKey(),
                'product_sku' => (string) $line->product->sku,
                'product_name' => (string) $line->product->name,
                'quantity' => $line->quantity,
                'tax_affectation' => $line->taxAffectation->value,
                'tax_rate_bps' => $line->taxRateBasisPoints,
                'unit_price_cents' => $line->unitPriceCents,
                'gross_total_cents' => $line->grossTotalCents,
                'discount_cents' => $line->discountCents,
                'net_value_cents' => $line->netValueCents,
                'tax_cents' => $line->taxCents,
                'total_cents' => $line->totalCents,
            ],
            $this->pricing->lines,
        );

        usort($items, fn (array $left, array $right): int => $left['product_id'] <=> $right['product_id']);

        return $items;
    }

    /** @return array<string, mixed> */
    private function lineToArray(OrderLinePricing $line): array
    {
        return [
            'product_id' => $line->product->getKey(),
            'name' => $line->product->name,
            'slug' => $line->product->slug,
            'url' => route('shop.product', $line->product->slug),
            'image_url' => $line->product->main_image_url,
            'description' => $line->product->short_description,
            'quantity' => $line->quantity,
            'tax_affectation' => $line->taxAffectation->value,
            'tax_label' => $line->taxAffectation->label(),
            'unit_price_cents' => $line->unitPriceCents,
            'total_cents' => $line->totalCents,
            'formatted_unit_price' => $this->format($line->unitPriceCents),
            'formatted_total' => $this->format($line->totalCents),
        ];
    }

    private function format(int $cents): string
    {
        return Money::fromCents($cents)->formatted();
    }
}
