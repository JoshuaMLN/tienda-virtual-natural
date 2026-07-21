<?php

namespace App\Enums;

enum CheckoutChangeType: string
{
    case ProductRemoved = 'product_removed';
    case ProductQuantityReduced = 'product_quantity_reduced';
    case ProductPriceChanged = 'product_price_changed';
    case ProductTaxChanged = 'product_tax_changed';
    case ProductIdentityChanged = 'product_identity_changed';
    case DeliveryUnavailable = 'delivery_unavailable';
    case DeliveryBaseFeeChanged = 'delivery_base_fee_changed';
    case ShippingFeeChanged = 'shipping_fee_changed';
    case FreeShippingChanged = 'free_shipping_changed';
    case DeliveryEstimateChanged = 'delivery_estimate_changed';
    case PickupDetailsChanged = 'pickup_details_changed';
    case ProductsSubtotalChanged = 'products_subtotal_changed';
    case DiscountChanged = 'discount_changed';
    case ShippingNetValueChanged = 'shipping_net_value_changed';
    case ShippingTaxChanged = 'shipping_tax_changed';
    case TaxableValueChanged = 'taxable_value_changed';
    case ExemptValueChanged = 'exempt_value_changed';
    case UnaffectedValueChanged = 'unaffected_value_changed';
    case NetValueChanged = 'net_value_changed';
    case TaxChanged = 'tax_changed';
    case TotalChanged = 'total_changed';
    case DeliveryDetailsChanged = 'delivery_details_changed';
    case TermsChanged = 'terms_changed';

    public function scope(): string
    {
        return match ($this) {
            self::ProductRemoved,
            self::ProductQuantityReduced,
            self::ProductPriceChanged,
            self::ProductTaxChanged,
            self::ProductIdentityChanged => 'product',
            self::DeliveryUnavailable,
            self::DeliveryBaseFeeChanged,
            self::ShippingFeeChanged,
            self::FreeShippingChanged,
            self::DeliveryEstimateChanged,
            self::PickupDetailsChanged,
            self::DeliveryDetailsChanged => 'delivery',
            self::TermsChanged => 'legal',
            default => 'amounts',
        };
    }

    public function label(): string
    {
        return match ($this) {
            self::ProductRemoved => 'Producto retirado',
            self::ProductQuantityReduced => 'Cantidad ajustada',
            self::ProductPriceChanged => 'Precio actualizado',
            self::ProductTaxChanged => 'Afectacion tributaria actualizada',
            self::ProductIdentityChanged => 'Datos del producto actualizados',
            self::DeliveryUnavailable => 'Entrega no disponible',
            self::DeliveryBaseFeeChanged => 'Tarifa base actualizada',
            self::ShippingFeeChanged => 'Costo de envio actualizado',
            self::FreeShippingChanged => 'Condicion de envio gratis actualizada',
            self::DeliveryEstimateChanged => 'Fecha estimada actualizada',
            self::PickupDetailsChanged => 'Datos de recojo actualizados',
            self::ProductsSubtotalChanged => 'Subtotal actualizado',
            self::DiscountChanged => 'Descuento actualizado',
            self::ShippingNetValueChanged => 'Valor de envio actualizado',
            self::ShippingTaxChanged => 'IGV del envio actualizado',
            self::TaxableValueChanged => 'Valor gravado actualizado',
            self::ExemptValueChanged => 'Valor exonerado actualizado',
            self::UnaffectedValueChanged => 'Valor inafecto actualizado',
            self::NetValueChanged => 'Valor de venta actualizado',
            self::TaxChanged => 'IGV actualizado',
            self::TotalChanged => 'Total actualizado',
            self::DeliveryDetailsChanged => 'Datos de entrega actualizados',
            self::TermsChanged => 'Terminos y condiciones actualizados',
        };
    }
}
