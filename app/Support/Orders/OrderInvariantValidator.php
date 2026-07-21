<?php

namespace App\Support\Orders;

use App\Enums\DeliveryMethod;
use App\Enums\DeliveryStatus;
use App\Enums\FiscalDocumentType;
use App\Enums\FiscalIdentityDocumentType;
use App\Enums\OrderStatus;
use App\Enums\PaymentStatus;
use App\Enums\TaxAffectation;
use App\Support\Fiscal\FiscalIdentityDocument;
use App\Support\Tax\TaxCalculator;
use DomainException;
use UnitEnum;
use ValueError;

class OrderInvariantValidator
{
    public function __construct(
        private readonly TaxCalculator $taxCalculator,
    ) {}

    /**
     * @param  array<string, mixed>  $order
     * @param  list<array<string, mixed>>  $items
     */
    public function validate(array $order, array $items): void
    {
        $this->validateInitialStates($order);
        $this->validatePendingPaymentOwner($order);
        $this->validateCustomerAndDelivery($order);
        $this->validateFiscalRequest($order);
        $this->validateCheckoutSnapshot($order);
        $this->validateAmounts($order, $items);
    }

    /** @param array<string, mixed> $order */
    private function validatePendingPaymentOwner(array $order): void
    {
        if (! array_key_exists('pending_payment_owner_id', $order)) {
            return;
        }

        if (! is_int($order['pending_payment_owner_id'])
            || $order['pending_payment_owner_id'] < 1
            || (int) ($order['user_id'] ?? 0) !== $order['pending_payment_owner_id']) {
            throw new DomainException('La reserva de checkout debe pertenecer al mismo cliente del pedido.');
        }
    }

    /** @param array<string, mixed> $order */
    private function validateCheckoutSnapshot(array $order): void
    {
        $fields = [
            'checkout_idempotency_key',
            'checkout_review_reference',
            'terms_document_id',
            'terms_document_version',
            'terms_content_fingerprint',
            'terms_accepted_at',
            'terms_snapshot',
        ];
        $present = array_filter($fields, fn (string $field): bool => array_key_exists($field, $order));

        if ($present === []) {
            return;
        }

        if (count($present) !== count($fields)) {
            throw new DomainException('El snapshot de confirmacion del checkout debe guardarse completo.');
        }

        if (! is_string($order['checkout_idempotency_key'])
            || preg_match('/^[0-9a-f]{8}(?:-[0-9a-f]{4}){3}-[0-9a-f]{12}$/', $order['checkout_idempotency_key']) !== 1
            || ! is_string($order['checkout_review_reference'])
            || preg_match('/^[a-f0-9]{64}$/', $order['checkout_review_reference']) !== 1
            || ! is_int($order['terms_document_id'])
            || $order['terms_document_id'] < 1
            || ! is_int($order['terms_document_version'])
            || $order['terms_document_version'] < 1
            || ! is_string($order['terms_content_fingerprint'])
            || preg_match('/^[a-f0-9]{64}$/', $order['terms_content_fingerprint']) !== 1
            || ! $order['terms_accepted_at'] instanceof \DateTimeInterface
            || ! is_array($order['terms_snapshot'])
            || trim((string) ($order['terms_snapshot']['body'] ?? '')) === '') {
            throw new DomainException('El snapshot de confirmacion del checkout no es valido.');
        }

        $snapshot = $order['terms_snapshot'];
        $snapshotPayload = [
            'id' => (int) ($snapshot['document_id'] ?? 0),
            'type' => (string) ($snapshot['type'] ?? ''),
            'version' => (int) ($snapshot['version'] ?? 0),
            'title' => (string) ($snapshot['title'] ?? ''),
            'body' => (string) ($snapshot['body'] ?? ''),
            'published_at' => $snapshot['published_at'] ?? null,
        ];
        $snapshotFingerprint = hash('sha256', json_encode(
            $snapshotPayload,
            JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR,
        ));

        if ($snapshotPayload['id'] !== $order['terms_document_id']
            || $snapshotPayload['version'] !== $order['terms_document_version']
            || $snapshotPayload['type'] !== 'terms'
            || $snapshotPayload['title'] === ''
            || ! hash_equals($order['terms_content_fingerprint'], $snapshotFingerprint)) {
            throw new DomainException('La huella legal no coincide con el snapshot de terminos.');
        }
    }

    /** @param array<string, mixed> $order */
    private function validateInitialStates(array $order): void
    {
        $expected = [
            'order_status' => OrderStatus::PendingPayment->value,
            'payment_status' => PaymentStatus::Pending->value,
            'delivery_status' => DeliveryStatus::Pending->value,
        ];

        foreach ($expected as $column => $value) {
            if ($this->enumValue($order[$column] ?? null) !== $value) {
                throw new DomainException('Todo pedido debe crearse en sus estados iniciales.');
            }
        }
    }

    /** @param array<string, mixed> $order */
    private function validateCustomerAndDelivery(array $order): void
    {
        if (empty($order['user_id'])) {
            throw new DomainException('El pedido debe pertenecer a una cuenta de cliente.');
        }

        $this->requireStrings($order, ['customer_name', 'customer_email']);

        if (! filter_var($order['customer_email'], FILTER_VALIDATE_EMAIL)) {
            throw new DomainException('El correo del cliente no es valido.');
        }

        $method = $this->enum(DeliveryMethod::class, $order['delivery_method'] ?? null, 'La modalidad de entrega no es valida.');

        if ($method === DeliveryMethod::HomeDelivery) {
            $this->requireStrings($order, [
                'delivery_recipient_name',
                'delivery_phone',
                'delivery_department',
                'delivery_province',
                'delivery_district',
                'delivery_ubigeo',
                'delivery_address',
            ]);

            if (! preg_match('/^\d{6}$/', (string) $order['delivery_ubigeo'])) {
                throw new DomainException('El UBIGEO de entrega debe tener seis digitos.');
            }
        } else {
            $this->requireStrings($order, ['pickup_address']);
        }

        $minimum = $this->integer($order, 'delivery_business_days_min');
        $maximum = $this->integer($order, 'delivery_business_days_max');

        if ($minimum < 1 || $maximum < $minimum) {
            throw new DomainException('El plazo de entrega en dias de atencion no es valido.');
        }

        if (($order['delivery_estimated_from'] ?? null) !== null || ($order['delivery_estimated_to'] ?? null) !== null) {
            throw new DomainException('Las fechas estimadas se definen al confirmar el pago.');
        }
    }

    /** @param array<string, mixed> $order */
    private function validateFiscalRequest(array $order): void
    {
        $type = $this->enum(FiscalDocumentType::class, $order['fiscal_document_type'] ?? null, 'El tipo de comprobante solicitado no es valido.');

        if (! $type->isSaleDocument()) {
            throw new DomainException('El pedido solo puede solicitar boleta o factura.');
        }

        $identity = $this->enum(
            FiscalIdentityDocumentType::class,
            $order['fiscal_identity_document_type'] ?? null,
            'El documento fiscal de identidad no es valido.',
        );

        $this->requireStrings($order, ['fiscal_identity_document_number', 'fiscal_email']);

        if (! filter_var($order['fiscal_email'], FILTER_VALIDATE_EMAIL)) {
            throw new DomainException('El correo fiscal no es valido.');
        }

        if (! FiscalIdentityDocument::isValid($identity, $order['fiscal_identity_document_number'])) {
            throw new DomainException(FiscalIdentityDocument::invalidMessage($identity));
        }

        if ($type === FiscalDocumentType::Invoice) {
            if ($identity !== FiscalIdentityDocumentType::Ruc) {
                throw new DomainException('La factura requiere un RUC de once digitos.');
            }

            $this->requireStrings($order, ['fiscal_business_name', 'fiscal_address']);

            if ($this->hasValue($order, 'fiscal_first_names') || $this->hasValue($order, 'fiscal_last_names')) {
                throw new DomainException('La factura no debe incluir nombres personales para la boleta.');
            }

            return;
        }

        if ($identity === FiscalIdentityDocumentType::Ruc) {
            throw new DomainException('La boleta requiere un documento personal.');
        }

        $this->requireStrings($order, ['fiscal_first_names', 'fiscal_last_names']);

        if ($this->hasValue($order, 'fiscal_business_name') || $this->hasValue($order, 'fiscal_address')) {
            throw new DomainException('La boleta no debe incluir datos exclusivos de una factura.');
        }
    }

    /**
     * @param  array<string, mixed>  $order
     * @param  list<array<string, mixed>>  $items
     */
    private function validateAmounts(array $order, array $items): void
    {
        if ($items === []) {
            throw new DomainException('El pedido debe contener al menos un producto.');
        }

        $productsSubtotal = 0;
        $discount = 0;
        $taxable = 0;
        $exempt = 0;
        $unaffected = 0;
        $tax = 0;

        foreach ($items as $item) {
            $this->requireStrings($item, ['product_sku', 'product_name', 'sale_unit']);
            $quantity = $this->integer($item, 'quantity');
            $unitPrice = $this->money($item, 'unit_price_cents');
            $gross = $this->money($item, 'gross_total_cents');
            $itemDiscount = $this->money($item, 'discount_cents');
            $net = $this->money($item, 'net_value_cents');
            $itemTax = $this->money($item, 'tax_cents');
            $total = $this->money($item, 'total_cents');

            if ($quantity < 1 || $gross !== $unitPrice * $quantity || $itemDiscount > $gross) {
                throw new DomainException('Los importes y la cantidad del item no son consistentes.');
            }

            $affectation = $this->enum(TaxAffectation::class, $item['tax_affectation'] ?? null, 'La afectacion tributaria del item no es valida.');
            $rate = $this->integer($item, 'tax_rate_bps');
            $breakdown = $this->taxCalculator->fromTaxIncluded($gross, $affectation, $itemDiscount, $rate);

            if ($rate !== $breakdown->rateBasisPoints
                || $net !== $breakdown->netValueCents()
                || $itemTax !== $breakdown->taxCents
                || $total !== $breakdown->totalCents) {
                throw new DomainException('El desglose tributario del item no coincide con la politica de calculo.');
            }

            $productsSubtotal += $gross;
            $discount += $itemDiscount;
            $taxable += $breakdown->taxableValueCents;
            $exempt += $breakdown->exemptValueCents;
            $unaffected += $breakdown->unaffectedValueCents;
            $tax += $breakdown->taxCents;
        }

        $shippingFee = $this->money($order, 'shipping_fee_cents');
        $shippingAffectation = $this->enum(TaxAffectation::class, $order['shipping_tax_affectation'] ?? null, 'La afectacion tributaria del envio no es valida.');
        $shippingRate = $this->integer($order, 'shipping_tax_rate_bps');
        $shipping = $this->taxCalculator->fromTaxIncluded($shippingFee, $shippingAffectation, rateBasisPoints: $shippingRate);

        if ($shippingRate !== $shipping->rateBasisPoints
            || $this->money($order, 'shipping_net_value_cents') !== $shipping->netValueCents()
            || $this->money($order, 'shipping_tax_cents') !== $shipping->taxCents) {
            throw new DomainException('El desglose tributario del envio no es consistente.');
        }

        $taxable += $shipping->taxableValueCents;
        $exempt += $shipping->exemptValueCents;
        $unaffected += $shipping->unaffectedValueCents;
        $tax += $shipping->taxCents;
        $net = $taxable + $exempt + $unaffected;
        $total = $productsSubtotal - $discount + $shippingFee;

        $expected = [
            'products_subtotal_cents' => $productsSubtotal,
            'discount_cents' => $discount,
            'taxable_value_cents' => $taxable,
            'exempt_value_cents' => $exempt,
            'unaffected_value_cents' => $unaffected,
            'net_value_cents' => $net,
            'tax_cents' => $tax,
            'total_cents' => $total,
        ];

        foreach ($expected as $column => $value) {
            if ($this->money($order, $column) !== $value) {
                throw new DomainException("El importe {$column} del pedido no coincide con sus items.");
            }
        }
    }

    /**
     * @template T of UnitEnum
     *
     * @param  class-string<T>  $enum
     * @return T
     */
    private function enum(string $enum, mixed $value, string $message): UnitEnum
    {
        if ($value instanceof $enum) {
            return $value;
        }

        try {
            return $enum::from((string) $value);
        } catch (ValueError) {
            throw new DomainException($message);
        }
    }

    private function enumValue(mixed $value): mixed
    {
        return $value instanceof \BackedEnum ? $value->value : $value;
    }

    /** @param array<string, mixed> $attributes */
    private function integer(array $attributes, string $column): int
    {
        $value = $attributes[$column] ?? null;

        if (! is_int($value) && ! (is_string($value) && preg_match('/^\d+$/', $value))) {
            throw new DomainException("El campo {$column} debe ser un entero.");
        }

        return (int) $value;
    }

    /** @param array<string, mixed> $attributes */
    private function money(array $attributes, string $column): int
    {
        $value = $this->integer($attributes, $column);

        if ($value < 0) {
            throw new DomainException("El importe {$column} no puede ser negativo.");
        }

        return $value;
    }

    /**
     * @param  array<string, mixed>  $attributes
     * @param  list<string>  $columns
     */
    private function requireStrings(array $attributes, array $columns): void
    {
        foreach ($columns as $column) {
            if (! is_string($attributes[$column] ?? null) || trim($attributes[$column]) === '') {
                throw new DomainException("El campo {$column} es obligatorio.");
            }
        }
    }

    /** @param array<string, mixed> $attributes */
    private function hasValue(array $attributes, string $column): bool
    {
        return is_string($attributes[$column] ?? null) && trim($attributes[$column]) !== '';
    }
}
