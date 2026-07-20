@props(['checkout'])

<section class="checkout-card p-3 p-lg-4" aria-labelledby="checkout-products-title">
    <div class="d-flex align-items-center justify-content-between gap-3 mb-3">
        <h2 class="h5 fw-black mb-0" id="checkout-products-title">Productos</h2>
        <span class="small text-muted" data-checkout-total-quantity>{{ $checkout['total_quantity'] }} unidades</span>
    </div>

    <div class="checkout-product-list" data-checkout-items>
        @foreach($checkout['items'] as $item)
            <article class="checkout-product-row" data-checkout-item="{{ $item['product_id'] }}">
                <a class="checkout-product-image" href="{{ $item['url'] }}" aria-label="Ver {{ $item['name'] }}">
                    <img src="{{ $item['image_url'] }}" alt="{{ $item['name'] }}">
                </a>
                <div class="checkout-product-content">
                    <a class="fw-bold" href="{{ $item['url'] }}">{{ $item['name'] }}</a>
                    @if($item['description'])
                        <p class="small text-muted mb-1">{{ $item['description'] }}</p>
                    @endif
                    <div class="small text-muted">
                        {{ $item['quantity'] }} x {{ $item['formatted_unit_price'] }}
                        <span class="mx-1" aria-hidden="true">&middot;</span>
                        {{ $item['tax_label'] }}
                    </div>
                </div>
                <strong class="checkout-product-total">{{ $item['formatted_total'] }}</strong>
            </article>
        @endforeach
    </div>
</section>
