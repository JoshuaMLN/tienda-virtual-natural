<?php

namespace Tests\Unit\Checkout;

use App\Enums\DeliveryMethod;
use App\Support\Checkout\CheckoutDeliverySnapshot;
use PHPUnit\Framework\TestCase;

class CheckoutDeliverySnapshotTest extends TestCase
{
    public function test_a_home_delivery_snapshot_round_trips_with_complete_item_snapshots(): void
    {
        $snapshot = $this->homeSnapshot();

        $restored = CheckoutDeliverySnapshot::fromArray($snapshot->toArray());

        $this->assertNotNull($restored);
        $this->assertSame(DeliveryMethod::HomeDelivery, $restored->method);
        $this->assertSame(15, $restored->addressId);
        $this->assertSame('150140', $restored->ubigeo);
        $this->assertSame('2026-07-21', $restored->estimatedFrom);
        $this->assertSame('2026-07-22', $restored->estimatedTo);
        $this->assertNull($restored->pickupHoldDays);
        $this->assertSame(12980, $restored->amounts['total_cents']);
        $this->assertSame([
            'product_id' => 10,
            'product_sku' => 'SKU-OMEGA-120',
            'product_name' => 'Omega 3 Premium',
            'quantity' => 1,
            'tax_affectation' => 'taxed',
            'tax_rate_bps' => 1800,
            'unit_price_cents' => 11800,
            'gross_total_cents' => 11800,
            'discount_cents' => 0,
            'net_value_cents' => 10000,
            'tax_cents' => 1800,
            'total_cents' => 11800,
        ], $restored->items[0]);
        $this->assertSame($snapshot->fingerprint(), $restored->fingerprint());
    }

    public function test_altered_amounts_delivery_identity_or_items_invalidate_the_snapshot(): void
    {
        $data = $this->homeSnapshot()->toArray();
        $data['amounts']['shipping_fee_cents'] = 1;

        $this->assertNull(CheckoutDeliverySnapshot::fromArray($data));

        $data = $this->homeSnapshot()->toArray();
        $data['address_id'] = null;
        $data['fingerprint'] = hash('sha256', 'not-a-valid-domain-payload');

        $this->assertNull(CheckoutDeliverySnapshot::fromArray($data));

        $data = $this->homeSnapshot()->toArray();
        $data['items'][0]['quantity'] = 2;

        $this->assertNull(CheckoutDeliverySnapshot::fromArray($data));

        $data = $this->homeSnapshot()->toArray();
        $data['estimated_to'] = '2026-07-20';

        $this->assertNull(CheckoutDeliverySnapshot::fromArray($data));
    }

    public function test_snapshots_distinguish_different_carts_with_the_same_aggregate_total(): void
    {
        $omega = $this->homeSnapshot();
        $magnesium = $this->homeSnapshot([
            'product_id' => 20,
            'product_sku' => 'SKU-MAGNESIO-120',
            'product_name' => 'Magnesio Citrato',
        ]);

        $this->assertSame($omega->amounts, $magnesium->amounts);
        $this->assertNotSame($omega->items, $magnesium->items);
        $this->assertNotSame($omega->fingerprint(), $magnesium->fingerprint());
    }

    public function test_pickup_requires_and_preserves_its_current_hold_days(): void
    {
        $snapshot = new CheckoutDeliverySnapshot(
            method: DeliveryMethod::Pickup,
            addressId: null,
            ubigeo: null,
            baseFeeCents: 0,
            hasFreeShipping: false,
            deliveryBusinessDaysMin: 1,
            deliveryBusinessDaysMax: 2,
            estimatedFrom: '2026-07-21',
            estimatedTo: '2026-07-22',
            pickupAddress: 'Av. Javier Prado 1234, San Isidro',
            pickupHoldDays: 14,
            amounts: $this->amounts(shippingFeeCents: 0, totalCents: 11800),
            items: [$this->itemSnapshot()],
        );

        $restored = CheckoutDeliverySnapshot::fromArray($snapshot->toArray());

        $this->assertNotNull($restored);
        $this->assertSame(14, $restored->pickupHoldDays);
        $this->assertSame('Av. Javier Prado 1234, San Isidro', $restored->pickupAddress);
        $this->assertSame($snapshot->fingerprint(), $restored->fingerprint());
    }

    /** @param array<string, int|string> $itemOverrides */
    private function homeSnapshot(array $itemOverrides = []): CheckoutDeliverySnapshot
    {
        return new CheckoutDeliverySnapshot(
            method: DeliveryMethod::HomeDelivery,
            addressId: 15,
            ubigeo: '150140',
            baseFeeCents: 1180,
            hasFreeShipping: false,
            deliveryBusinessDaysMin: 1,
            deliveryBusinessDaysMax: 2,
            estimatedFrom: '2026-07-21',
            estimatedTo: '2026-07-22',
            pickupAddress: null,
            pickupHoldDays: null,
            amounts: $this->amounts(),
            items: [$this->itemSnapshot($itemOverrides)],
        );
    }

    /** @param array<string, int|string> $overrides
     * @return array<string, int|string>
     */
    private function itemSnapshot(array $overrides = []): array
    {
        return [
            'product_id' => 10,
            'product_sku' => 'SKU-OMEGA-120',
            'product_name' => 'Omega 3 Premium',
            'quantity' => 1,
            'tax_affectation' => 'taxed',
            'tax_rate_bps' => 1800,
            'unit_price_cents' => 11800,
            'gross_total_cents' => 11800,
            'discount_cents' => 0,
            'net_value_cents' => 10000,
            'tax_cents' => 1800,
            'total_cents' => 11800,
            ...$overrides,
        ];
    }

    /** @return array<string, int> */
    private function amounts(int $shippingFeeCents = 1180, int $totalCents = 12980): array
    {
        return [
            'products_subtotal_cents' => 11800,
            'discount_cents' => 0,
            'shipping_fee_cents' => $shippingFeeCents,
            'shipping_net_value_cents' => $shippingFeeCents === 0 ? 0 : 1000,
            'shipping_tax_cents' => $shippingFeeCents === 0 ? 0 : 180,
            'taxable_value_cents' => $shippingFeeCents === 0 ? 10000 : 11000,
            'exempt_value_cents' => 0,
            'unaffected_value_cents' => 0,
            'net_value_cents' => $shippingFeeCents === 0 ? 10000 : 11000,
            'tax_cents' => $shippingFeeCents === 0 ? 1800 : 1980,
            'total_cents' => $totalCents,
        ];
    }
}
