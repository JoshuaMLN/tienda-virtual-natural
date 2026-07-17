@props(['checkout'])

@php($amounts = $checkout['amounts'])

<aside class="checkout-card p-3 p-lg-4 checkout-summary">
    <h2 class="h5 fw-black mb-3">Resumen de compra</h2>

    <div class="d-grid gap-2 small">
        <div class="d-flex justify-content-between gap-3">
            <span>Productos</span>
            <strong>{{ $checkout['product_count'] }} ({{ $checkout['total_quantity'] }} unidades)</strong>
        </div>
        <div class="d-flex justify-content-between gap-3">
            <span>Subtotal de productos</span>
            <strong data-checkout-subtotal>{{ $amounts['formatted_products_subtotal'] }}</strong>
        </div>
        <div class="d-flex justify-content-between gap-3">
            <span>Envio</span>
            <strong>Por calcular</strong>
        </div>

        <hr class="my-2">

        <div class="text-muted fw-bold mb-1">Desglose tributario</div>
        @if($amounts['taxable_value_cents'] > 0)
            <div class="d-flex justify-content-between gap-3">
                <span>Valor de venta gravado</span>
                <strong>{{ $amounts['formatted_taxable_value'] }}</strong>
            </div>
        @endif
        @if($amounts['exempt_value_cents'] > 0)
            <div class="d-flex justify-content-between gap-3">
                <span>Valor de venta exonerado</span>
                <strong>{{ $amounts['formatted_exempt_value'] }}</strong>
            </div>
        @endif
        @if($amounts['unaffected_value_cents'] > 0)
            <div class="d-flex justify-content-between gap-3">
                <span>Valor de venta inafecto</span>
                <strong>{{ $amounts['formatted_unaffected_value'] }}</strong>
            </div>
        @endif
        <div class="d-flex justify-content-between gap-3">
            <span>IGV incluido</span>
            <strong data-checkout-tax>{{ $amounts['formatted_tax'] }}</strong>
        </div>

        <hr class="my-2">

        <div class="d-flex justify-content-between align-items-end gap-3 fs-5">
            <span>Total actual</span>
            <strong data-checkout-total>{{ $amounts['formatted_total'] }}</strong>
        </div>
        <span class="text-muted text-end">El envio se sumara al elegir la modalidad de entrega.</span>
    </div>

    <a class="btn btn-vn-outline w-100 mt-4" href="{{ route('shop.cart') }}">
        <i class="bi bi-arrow-left me-1" aria-hidden="true"></i>Volver al carrito
    </a>
</aside>
