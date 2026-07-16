<?php

namespace Tests\Unit\Orders;

use App\Enums\TaxAffectation;
use App\Models\Product;
use App\Support\Money\DiscountAllocator;
use App\Support\Money\Money;
use App\Support\Orders\Pricing\OrderPricingService;
use App\Support\Tax\TaxCalculator;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;

class MoneyTaxPricingTest extends TestCase
{
    public function test_money_converts_rounds_formats_and_operates_in_cents(): void
    {
        $amount = Money::fromDecimal('79.905');

        $this->assertSame(7_991, $amount->cents);
        $this->assertSame('79.91', $amount->decimal());
        $this->assertSame('S/ 79.91', $amount->formatted());
        $this->assertSame(9_091, $amount->add(Money::fromCents(1_100))->cents);
        $this->assertSame(7_891, $amount->subtract(Money::fromCents(100))->cents);
        $this->assertSame(23_973, $amount->multiply(3)->cents);
        $this->assertSame(7_990, Money::fromDecimal('79.9')->cents);
    }

    public function test_money_rejects_values_that_cannot_represent_a_positive_amount(): void
    {
        $this->expectException(InvalidArgumentException::class);

        Money::fromDecimal('-0.01');
    }

    public function test_tax_calculator_extracts_igv_from_a_tax_included_price(): void
    {
        $breakdown = (new TaxCalculator)->fromTaxIncluded(
            grossCents: 11_800,
            affectation: TaxAffectation::Taxed,
        );

        $this->assertSame(10_000, $breakdown->taxableValueCents);
        $this->assertSame(1_800, $breakdown->taxCents);
        $this->assertSame(11_800, $breakdown->totalCents);
        $this->assertSame(10_000, $breakdown->netValueCents());
        $this->assertSame(1_800, $breakdown->rateBasisPoints);
    }

    public function test_exempt_and_unaffected_amounts_never_generate_igv(): void
    {
        $calculator = new TaxCalculator;
        $exempt = $calculator->fromTaxIncluded(5_000, TaxAffectation::Exempt, 125);
        $unaffected = $calculator->fromTaxIncluded(2_500, TaxAffectation::Unaffected, 25);

        $this->assertSame(4_875, $exempt->exemptValueCents);
        $this->assertSame(0, $exempt->taxableValueCents);
        $this->assertSame(0, $exempt->unaffectedValueCents);
        $this->assertSame(0, $exempt->taxCents);
        $this->assertSame(0, $exempt->rateBasisPoints);

        $this->assertSame(2_475, $unaffected->unaffectedValueCents);
        $this->assertSame(0, $unaffected->taxableValueCents);
        $this->assertSame(0, $unaffected->exemptValueCents);
        $this->assertSame(0, $unaffected->taxCents);
        $this->assertSame(0, $unaffected->rateBasisPoints);
    }

    public function test_tax_rounding_preserves_every_cent_of_the_total(): void
    {
        $calculator = new TaxCalculator;

        foreach ([1, 2, 99, 100, 101, 118, 119, 1_000, 11_799] as $grossCents) {
            $breakdown = $calculator->fromTaxIncluded($grossCents, TaxAffectation::Taxed);

            $this->assertSame($grossCents, $breakdown->netValueCents() + $breakdown->taxCents);
        }

        $oneSol = $calculator->fromTaxIncluded(100, TaxAffectation::Taxed);

        $this->assertSame(85, $oneSol->taxableValueCents);
        $this->assertSame(15, $oneSol->taxCents);
    }

    public function test_discount_residues_are_allocated_deterministically_without_losing_cents(): void
    {
        $allocator = new DiscountAllocator;

        $firstRun = $allocator->allocate(2, [
            'first' => 100,
            'second' => 100,
            'third' => 100,
        ]);
        $secondRun = $allocator->allocate(2, [
            'first' => 100,
            'second' => 100,
            'third' => 100,
        ]);

        $this->assertSame([
            'first' => 1,
            'second' => 1,
            'third' => 0,
        ], $firstRun);
        $this->assertSame($firstRun, $secondRun);
        $this->assertSame(2, array_sum($firstRun));

        $weighted = $allocator->allocate(5, [100, 200, 300]);

        $this->assertSame([1, 2, 2], $weighted);
        $this->assertSame(5, array_sum($weighted));
    }

    public function test_mixed_pricing_reconciles_taxed_exempt_unaffected_shipping_and_discount_totals(): void
    {
        $service = new OrderPricingService(new DiscountAllocator, new TaxCalculator);
        $taxed = $this->product('Producto gravado', '118.00', TaxAffectation::Taxed);
        $exempt = $this->product('Producto exonerado', '50.00', TaxAffectation::Exempt);
        $unaffected = $this->product('Producto inafecto', '25.00', TaxAffectation::Unaffected);

        $pricing = $service->calculate(
            items: [
                ['product' => $taxed, 'quantity' => 1],
                ['product' => $exempt, 'quantity' => 2],
                ['product' => $unaffected, 'quantity' => 1],
            ],
            discountCents: 300,
            shippingFeeCents: 1_180,
        );

        $this->assertSame(24_300, $pricing->productsSubtotalCents);
        $this->assertSame(300, $pricing->discountCents);
        $this->assertSame(1_180, $pricing->shippingFeeCents);
        $this->assertSame(1_000, $pricing->shippingNetValueCents);
        $this->assertSame(180, $pricing->shippingTaxCents);
        $this->assertSame(10_876, $pricing->taxableValueCents);
        $this->assertSame(9_877, $pricing->exemptValueCents);
        $this->assertSame(2_469, $pricing->unaffectedValueCents);
        $this->assertSame(23_222, $pricing->netValueCents);
        $this->assertSame(1_958, $pricing->taxCents);
        $this->assertSame(25_180, $pricing->totalCents);
        $this->assertSame($pricing->totalCents, $pricing->netValueCents + $pricing->taxCents);

        $this->assertSame([146, 123, 31], array_map(
            static fn ($line): int => $line->discountCents,
            $pricing->lines,
        ));
        $this->assertSame([11_654, 9_877, 2_469], array_map(
            static fn ($line): int => $line->totalCents,
            $pricing->lines,
        ));
        $this->assertSame(24_000, array_sum(array_map(
            static fn ($line): int => $line->totalCents,
            $pricing->lines,
        )));
    }

    public function test_pricing_rejects_a_discount_greater_than_the_products_subtotal(): void
    {
        $service = new OrderPricingService(new DiscountAllocator, new TaxCalculator);

        $this->expectException(InvalidArgumentException::class);

        $service->calculate(
            [['product' => $this->product('Producto', '10.00', TaxAffectation::Taxed), 'quantity' => 1]],
            discountCents: 1_001,
        );
    }

    private function product(string $name, string $price, TaxAffectation $affectation): Product
    {
        return (new Product)->forceFill([
            'name' => $name,
            'sku' => strtoupper(str_replace(' ', '-', $name)),
            'price' => $price,
            'tax_affectation' => $affectation,
            'tax_rate_bps' => $affectation->taxRateBasisPoints(),
        ]);
    }
}
