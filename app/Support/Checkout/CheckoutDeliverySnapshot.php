<?php

namespace App\Support\Checkout;

use App\Enums\DeliveryMethod;
use App\Enums\TaxAffectation;
use ValueError;

final readonly class CheckoutDeliverySnapshot
{
    /**
     * @param  list<array<string, int|string>>  $items
     * @param  array<string, int>  $amounts
     */
    public function __construct(
        public DeliveryMethod $method,
        public ?int $addressId,
        public ?string $ubigeo,
        public int $baseFeeCents,
        public bool $hasFreeShipping,
        public int $deliveryBusinessDaysMin,
        public int $deliveryBusinessDaysMax,
        public string $estimatedFrom,
        public string $estimatedTo,
        public ?string $pickupAddress,
        public ?int $pickupHoldDays,
        public array $items,
        public array $amounts,
    ) {}

    /** @return array<string, mixed> */
    public function toArray(): array
    {
        return [
            ...$this->payload(),
            'fingerprint' => $this->fingerprint(),
        ];
    }

    public function fingerprint(): string
    {
        return hash('sha256', json_encode(
            $this->payload(),
            JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR,
        ));
    }

    /** @param array<string, mixed>|null $data */
    public static function fromArray(?array $data): ?self
    {
        if (
            ! is_array($data)
            || (int) ($data['version'] ?? 0) !== 2
            || ! is_array($data['items'] ?? null)
            || ! is_array($data['amounts'] ?? null)
        ) {
            return null;
        }

        try {
            $method = DeliveryMethod::from((string) ($data['method'] ?? ''));
        } catch (ValueError) {
            return null;
        }

        $items = self::restoreItems($data['items']);

        if ($items === null || $items === []) {
            return null;
        }

        $amounts = [];

        foreach ($data['amounts'] as $key => $value) {
            if (! is_string($key) || ! is_int($value) || $value < 0) {
                return null;
            }

            $amounts[$key] = $value;
        }

        $requiredAmounts = [
            'products_subtotal_cents',
            'discount_cents',
            'shipping_fee_cents',
            'shipping_net_value_cents',
            'shipping_tax_cents',
            'taxable_value_cents',
            'exempt_value_cents',
            'unaffected_value_cents',
            'net_value_cents',
            'tax_cents',
            'total_cents',
        ];

        if (array_diff($requiredAmounts, array_keys($amounts)) !== []) {
            return null;
        }

        $snapshot = new self(
            method: $method,
            addressId: isset($data['address_id']) ? (int) $data['address_id'] : null,
            ubigeo: is_string($data['ubigeo'] ?? null) ? $data['ubigeo'] : null,
            baseFeeCents: (int) ($data['base_fee_cents'] ?? -1),
            hasFreeShipping: (bool) ($data['has_free_shipping'] ?? false),
            deliveryBusinessDaysMin: (int) ($data['delivery_business_days_min'] ?? 0),
            deliveryBusinessDaysMax: (int) ($data['delivery_business_days_max'] ?? 0),
            estimatedFrom: is_string($data['estimated_from'] ?? null) ? $data['estimated_from'] : '',
            estimatedTo: is_string($data['estimated_to'] ?? null) ? $data['estimated_to'] : '',
            pickupAddress: is_string($data['pickup_address'] ?? null) ? $data['pickup_address'] : null,
            pickupHoldDays: isset($data['pickup_hold_days']) ? (int) $data['pickup_hold_days'] : null,
            items: $items,
            amounts: $amounts,
        );

        $fingerprint = $data['fingerprint'] ?? null;

        if (
            $snapshot->baseFeeCents < 0
            || $snapshot->deliveryBusinessDaysMin < 1
            || $snapshot->deliveryBusinessDaysMax < $snapshot->deliveryBusinessDaysMin
            || ! self::validDate($snapshot->estimatedFrom)
            || ! self::validDate($snapshot->estimatedTo)
            || $snapshot->estimatedTo < $snapshot->estimatedFrom
            || ! is_string($fingerprint)
            || ! hash_equals($snapshot->fingerprint(), $fingerprint)
            || $snapshot->amounts['products_subtotal_cents'] !== array_sum(array_column($snapshot->items, 'gross_total_cents'))
            || $snapshot->amounts['discount_cents'] !== array_sum(array_column($snapshot->items, 'discount_cents'))
        ) {
            return null;
        }

        if (
            ($snapshot->method === DeliveryMethod::HomeDelivery
                && (! preg_match('/^\d{6}$/', $snapshot->ubigeo ?? '')
                    || $snapshot->pickupAddress !== null
                    || $snapshot->pickupHoldDays !== null
                    || ! in_array(
                        $snapshot->amounts['shipping_fee_cents'],
                        [0, $snapshot->baseFeeCents],
                        true,
                    )))
            || ($snapshot->method === DeliveryMethod::Pickup
                && ($snapshot->addressId !== null
                    || $snapshot->ubigeo !== null
                    || $snapshot->baseFeeCents !== 0
                    || $snapshot->hasFreeShipping
                    || $snapshot->pickupAddress === null
                    || $snapshot->pickupAddress === ''
                    || $snapshot->pickupHoldDays === null
                    || $snapshot->pickupHoldDays < 1
                    || $snapshot->amounts['shipping_fee_cents'] !== 0))
        ) {
            return null;
        }

        return $snapshot;
    }

    /** @return array<string, mixed> */
    private function payload(): array
    {
        return [
            'version' => 2,
            'method' => $this->method->value,
            'address_id' => $this->addressId,
            'ubigeo' => $this->ubigeo,
            'base_fee_cents' => $this->baseFeeCents,
            'has_free_shipping' => $this->hasFreeShipping,
            'delivery_business_days_min' => $this->deliveryBusinessDaysMin,
            'delivery_business_days_max' => $this->deliveryBusinessDaysMax,
            'estimated_from' => $this->estimatedFrom,
            'estimated_to' => $this->estimatedTo,
            'pickup_address' => $this->pickupAddress,
            'pickup_hold_days' => $this->pickupHoldDays,
            'items' => $this->sortedItems(),
            'amounts' => $this->amounts,
        ];
    }

    /** @return list<array<string, int|string>> */
    private function sortedItems(): array
    {
        $items = $this->items;
        usort($items, fn (array $left, array $right): int => $left['product_id'] <=> $right['product_id']);

        return $items;
    }

    /**
     * @param  array<mixed>  $items
     * @return list<array<string, int|string>>|null
     */
    private static function restoreItems(array $items): ?array
    {
        $restored = [];
        $productIds = [];

        foreach ($items as $item) {
            if (! is_array($item)) {
                return null;
            }

            $stringFields = ['product_sku', 'product_name', 'tax_affectation'];
            $integerFields = [
                'product_id',
                'quantity',
                'tax_rate_bps',
                'unit_price_cents',
                'gross_total_cents',
                'discount_cents',
                'net_value_cents',
                'tax_cents',
                'total_cents',
            ];

            foreach ($stringFields as $field) {
                if (! is_string($item[$field] ?? null)) {
                    return null;
                }
            }

            foreach ($integerFields as $field) {
                if (! is_int($item[$field] ?? null) || $item[$field] < 0) {
                    return null;
                }
            }

            $taxAffectation = TaxAffectation::tryFrom($item['tax_affectation']);

            if (
                $item['product_id'] < 1
                || $item['quantity'] < 1
                || $item['product_name'] === ''
                || isset($productIds[$item['product_id']])
                || $taxAffectation === null
                || $item['tax_rate_bps'] !== $taxAffectation->taxRateBasisPoints()
                || $item['gross_total_cents'] !== $item['unit_price_cents'] * $item['quantity']
                || $item['total_cents'] !== $item['gross_total_cents'] - $item['discount_cents']
                || $item['total_cents'] !== $item['net_value_cents'] + $item['tax_cents']
            ) {
                return null;
            }

            $productIds[$item['product_id']] = true;
            $restored[] = [
                'product_id' => $item['product_id'],
                'product_sku' => $item['product_sku'],
                'product_name' => $item['product_name'],
                'quantity' => $item['quantity'],
                'tax_affectation' => $item['tax_affectation'],
                'tax_rate_bps' => $item['tax_rate_bps'],
                'unit_price_cents' => $item['unit_price_cents'],
                'gross_total_cents' => $item['gross_total_cents'],
                'discount_cents' => $item['discount_cents'],
                'net_value_cents' => $item['net_value_cents'],
                'tax_cents' => $item['tax_cents'],
                'total_cents' => $item['total_cents'],
            ];
        }

        usort($restored, fn (array $left, array $right): int => $left['product_id'] <=> $right['product_id']);

        return $restored;
    }

    private static function validDate(string $date): bool
    {
        if (! preg_match('/^(\d{4})-(\d{2})-(\d{2})$/', $date, $parts)) {
            return false;
        }

        return checkdate((int) $parts[2], (int) $parts[3], (int) $parts[1]);
    }
}
