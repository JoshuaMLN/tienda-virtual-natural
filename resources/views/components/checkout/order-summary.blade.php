@props(['checkout', 'delivery'])

@php
    $quote = $delivery['quote'];
    $amounts = data_get($quote, 'summary.amounts', $checkout['amounts']);
    $shippingLabel = 'Por calcular';

    if ($quote) {
        $shippingLabel = $quote['shipping_fee_cents'] === 0
            ? 'Gratis'
            : $quote['formatted_shipping_fee'];
    }
@endphp

<section class="checkout-card p-3 p-lg-4 checkout-summary" data-checkout-summary aria-busy="false" aria-labelledby="checkout-summary-title">
    <h2 class="h5 fw-black mb-3" id="checkout-summary-title">Resumen de compra</h2>

    <div class="d-grid gap-2 small">
        <div class="d-flex justify-content-between gap-3">
            <span>Productos</span>
            <strong data-checkout-products>{{ $checkout['product_count'] }} ({{ $checkout['total_quantity'] }} unidades)</strong>
        </div>
        <div class="d-flex justify-content-between gap-3">
            <span>Subtotal de productos</span>
            <strong data-checkout-subtotal>{{ $amounts['formatted_products_subtotal'] }}</strong>
        </div>
        <div class="d-flex justify-content-between gap-3">
            <span>Envio</span>
            <strong data-checkout-shipping>{{ $shippingLabel }}</strong>
        </div>

        <hr class="my-2">

        <div class="text-muted fw-bold mb-1">Desglose tributario</div>
        <div class="d-flex justify-content-between gap-3 {{ $amounts['taxable_value_cents'] > 0 ? '' : 'd-none' }}" data-checkout-taxable-row>
            <span>Valor de venta gravado</span>
            <strong data-checkout-taxable>{{ $amounts['formatted_taxable_value'] }}</strong>
        </div>
        <div class="d-flex justify-content-between gap-3 {{ $amounts['exempt_value_cents'] > 0 ? '' : 'd-none' }}" data-checkout-exempt-row>
            <span>Valor de venta exonerado</span>
            <strong data-checkout-exempt>{{ $amounts['formatted_exempt_value'] }}</strong>
        </div>
        <div class="d-flex justify-content-between gap-3 {{ $amounts['unaffected_value_cents'] > 0 ? '' : 'd-none' }}" data-checkout-unaffected-row>
            <span>Valor de venta inafecto</span>
            <strong data-checkout-unaffected>{{ $amounts['formatted_unaffected_value'] }}</strong>
        </div>
        <div class="d-flex justify-content-between gap-3">
            <span>IGV incluido</span>
            <strong data-checkout-tax>{{ $amounts['formatted_tax'] }}</strong>
        </div>

        <hr class="my-2">

        <div class="d-flex justify-content-between align-items-end gap-3 fs-5">
            <span>Total actual</span>
            <strong data-checkout-total>{{ $amounts['formatted_total'] }}</strong>
        </div>
        <span class="text-muted text-end" data-checkout-summary-note>
            {{ $quote ? $quote['method_label'].'. Tarifa final con IGV incluido.' : 'El envio se sumara al elegir la modalidad de entrega.' }}
        </span>
    </div>

    <a class="btn btn-vn-outline w-100 mt-4" href="{{ route('shop.cart') }}">
        <i class="bi bi-arrow-left me-1" aria-hidden="true"></i>Volver al carrito
    </a>
</section>
