<?php

namespace App\Support\Orders\Pricing;

use App\Enums\TaxAffectation;
use App\Models\Product;
use App\Support\Money\DiscountAllocator;
use App\Support\Money\Money;
use App\Support\Tax\TaxCalculator;
use InvalidArgumentException;

class OrderPricingService
{
    public function __construct(
        private readonly DiscountAllocator $discountAllocator,
        private readonly TaxCalculator $taxCalculator,
    ) {}

    /**
     * @param  iterable<array{product: Product, quantity: int}>  $items
     */
    public function calculate(
        iterable $items,
        int $discountCents = 0,
        int $shippingFeeCents = 0,
        TaxAffectation $shippingTaxAffectation = TaxAffectation::Taxed,
        ?int $shippingTaxRateBasisPoints = null,
    ): OrderPricing {
        if ($shippingFeeCents < 0) {
            throw new InvalidArgumentException('El envio no puede ser negativo.');
        }

        $prepared = [];
        $weights = [];

        foreach ($items as $item) {
            if (! isset($item['product'], $item['quantity']) || ! $item['product'] instanceof Product || $item['quantity'] <= 0) {
                throw new InvalidArgumentException('Cada item requiere un producto y una cantidad positiva.');
            }

            $unitPriceCents = Money::fromDecimal($item['product']->price)->cents;
            $grossTotalCents = $unitPriceCents * $item['quantity'];
            $prepared[] = [
                'product' => $item['product'],
                'quantity' => $item['quantity'],
                'unit_price_cents' => $unitPriceCents,
                'gross_total_cents' => $grossTotalCents,
            ];
            $weights[] = $grossTotalCents;
        }

        if ($prepared === []) {
            throw new InvalidArgumentException('El pedido requiere al menos un item.');
        }

        $discounts = $this->discountAllocator->allocate($discountCents, $weights);
        $lines = [];
        $taxableValueCents = 0;
        $exemptValueCents = 0;
        $unaffectedValueCents = 0;
        $taxCents = 0;

        foreach ($prepared as $index => $item) {
            $product = $item['product'];
            $affectation = $product->tax_affectation instanceof TaxAffectation
                ? $product->tax_affectation
                : TaxAffectation::from((string) $product->tax_affectation);
            $breakdown = $this->taxCalculator->fromTaxIncluded(
                $item['gross_total_cents'],
                $affectation,
                $discounts[$index],
                (int) $product->tax_rate_bps,
            );

            $lines[] = new OrderLinePricing(
                product: $product,
                quantity: $item['quantity'],
                taxAffectation: $affectation,
                taxRateBasisPoints: $breakdown->rateBasisPoints,
                unitPriceCents: $item['unit_price_cents'],
                grossTotalCents: $item['gross_total_cents'],
                discountCents: $breakdown->discountCents,
                netValueCents: $breakdown->netValueCents(),
                taxCents: $breakdown->taxCents,
                totalCents: $breakdown->totalCents,
            );
            $taxableValueCents += $breakdown->taxableValueCents;
            $exemptValueCents += $breakdown->exemptValueCents;
            $unaffectedValueCents += $breakdown->unaffectedValueCents;
            $taxCents += $breakdown->taxCents;
        }

        $shipping = $this->taxCalculator->fromTaxIncluded(
            $shippingFeeCents,
            $shippingTaxAffectation,
            rateBasisPoints: $shippingTaxRateBasisPoints,
        );
        $taxableValueCents += $shipping->taxableValueCents;
        $exemptValueCents += $shipping->exemptValueCents;
        $unaffectedValueCents += $shipping->unaffectedValueCents;
        $taxCents += $shipping->taxCents;
        $productsSubtotalCents = array_sum($weights);
        $netValueCents = $taxableValueCents + $exemptValueCents + $unaffectedValueCents;
        $totalCents = $productsSubtotalCents - $discountCents + $shippingFeeCents;

        return new OrderPricing(
            lines: $lines,
            productsSubtotalCents: $productsSubtotalCents,
            discountCents: $discountCents,
            shippingFeeCents: $shippingFeeCents,
            shippingNetValueCents: $shipping->netValueCents(),
            shippingTaxCents: $shipping->taxCents,
            taxableValueCents: $taxableValueCents,
            exemptValueCents: $exemptValueCents,
            unaffectedValueCents: $unaffectedValueCents,
            netValueCents: $netValueCents,
            taxCents: $taxCents,
            totalCents: $totalCents,
        );
    }
}
