@props([
    'items' => [],
    'subtotal' => 'S/ 176.60',
    'shipping' => 'Gratis',
    'discount' => '- S/ 15.00',
    'total' => 'S/ 161.60',
    'button' => 'Proceder al checkout',
    'href' => null,
])

<aside class="checkout-card p-3 p-lg-4">
    <h5 class="fw-black mb-3">Resumen del pedido</h5>
    @if($items)
        <div class="d-grid gap-3 mb-3">
            @foreach($items as $item)
                <div class="d-flex gap-3 align-items-center">
                    <div class="thumb-sm flex-shrink-0" style="background-image: url('{{ $item['image'] }}')"></div>
                    <div class="small flex-grow-1">
                        <strong>{{ $item['name'] }}</strong><br>
                        <span class="text-muted">{{ $item['qty'] ?? 1 }} unidad(es)</span>
                    </div>
                    <strong class="small">{{ $item['price'] }}</strong>
                </div>
            @endforeach
        </div>
    @endif
    <div class="d-grid gap-2 small">
        <div class="d-flex justify-content-between"><span>Subtotal</span><strong>{{ $subtotal }}</strong></div>
        <div class="d-flex justify-content-between"><span>Envio</span><strong>{{ $shipping }}</strong></div>
        <div class="d-flex justify-content-between"><span>Descuento</span><strong>{{ $discount }}</strong></div>
        <hr>
        <div class="d-flex justify-content-between fs-5"><span>Total</span><strong>{{ $total }}</strong></div>
        <span class="text-muted text-end">Impuestos incluidos cuando correspondan</span>
    </div>
    @if($href)
        <a class="btn btn-vn w-100 mt-4" href="{{ $href }}">{{ $button }}</a>
    @else
        <button class="btn btn-vn w-100 mt-4" type="button">{{ $button }}</button>
    @endif
</aside>
