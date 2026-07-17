@extends('layouts.checkout')

@section('title', 'Finalizar compra | VitaNatural')

@section('content')
<section
    class="container py-5"
    data-checkout-page
    data-cart-warnings-clear-url="{{ route('shop.cart.warnings.clear') }}"
>
    <nav class="small text-muted mb-3" aria-label="Migas de pan">
        <a href="{{ route('shop.index') }}">Inicio</a> &gt;
        <a href="{{ route('shop.cart') }}">Carrito</a> &gt;
        <span>Checkout</span>
    </nav>

    <div class="mb-4">
        <h1 class="section-title mb-1">Revisa tu compra</h1>
        <p class="text-muted mb-0">Confirma los productos e importes antes de ingresar los datos de entrega.</p>
    </div>

    @if($checkout['warnings'])
        <div class="alert alert-warning" data-cart-warnings role="alert">
            <div class="d-flex align-items-start justify-content-between gap-3">
                <div data-cart-warnings-list>
                    @foreach($checkout['warnings'] as $warning)
                        <div>{{ $warning }}</div>
                    @endforeach
                </div>
                <button class="btn-close flex-shrink-0" type="button" data-cart-warnings-clear aria-label="Cerrar aviso"></button>
            </div>
        </div>
    @endif

    @if(session('status') === 'checkout-contact-address-saved')
        <div class="alert alert-success d-flex align-items-center gap-2" role="status">
            <i class="bi bi-check-circle-fill" aria-hidden="true"></i>
            <span>Datos de contacto y direccion guardados para esta compra.</span>
        </div>
    @endif

    <div class="row g-4 align-items-start">
        <div class="col-lg-8">
            <x-checkout.contact-address-form :checkout-form="$checkoutForm" />

            <div class="checkout-card p-3 p-lg-4 mt-4">
                <div class="d-flex align-items-center justify-content-between gap-3 mb-3">
                    <h2 class="h5 fw-black mb-0">Productos</h2>
                    <span class="small text-muted">{{ $checkout['total_quantity'] }} unidades</span>
                </div>

                <div class="checkout-product-list">
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
            </div>
        </div>
        <div class="col-lg-4">
            <x-checkout.order-summary :checkout="$checkout" />
        </div>
    </div>
</section>
@endsection
