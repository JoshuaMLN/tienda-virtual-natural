@extends('layouts.checkout')

@section('title', 'Finalizar compra | VitaNatural')

@section('content')
@php
    $activeStep = $checkoutForm['active_step'];
    $maxStep = $checkoutForm['max_step'];
    $sidebarAmounts = data_get($delivery, 'quote.summary.amounts', $checkout['amounts']);
    $hasQuoteConflict = ($activeStep === 1 && $errors->getBag('checkout')->has('quote_reference'))
        || ($activeStep === 2 && $errors->getBag('checkoutReview')->has('quote_reference'));
@endphp
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
        <h1 class="section-title mb-1">Finalizar compra</h1>
        <p class="text-muted mb-0">Completa tus datos y revisa el pedido antes de continuar al pago.</p>
    </div>

    @if(! $hasQuoteConflict)
        <div class="alert alert-warning {{ $checkout['warnings'] ? '' : 'd-none' }}" data-cart-warnings data-checkout-global-warnings role="alert">
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

    <x-checkout.progress :active-step="$activeStep" :max-step="$maxStep" />

    <div class="checkout-layout mt-4">
        <aside class="checkout-sidebar" aria-label="Resumen del pedido">
            <details class="checkout-overview" data-checkout-overview open>
                <summary>
                    <span>
                        <i class="bi bi-bag-check" aria-hidden="true"></i>
                        Resumen y productos
                    </span>
                    <strong data-checkout-overview-total>{{ $sidebarAmounts['formatted_total'] }}</strong>
                </summary>
                <div class="checkout-sidebar-stack">
                    <x-checkout.order-summary :checkout="$checkout" :delivery="$delivery" />
                    <x-checkout.product-list :checkout="$checkout" />
                </div>
            </details>
        </aside>

        <div class="checkout-main">
            <div class="checkout-stage" data-checkout-stage="1" @if($activeStep !== 1) hidden @endif>
                <div class="checkout-coverage-note mb-4" role="note">
                    <i class="bi bi-geo-alt-fill" aria-hidden="true"></i>
                    <div>
                        <strong>Entrega disponible solo en Lima Metropolitana y Callao.</strong>
                        @if($delivery['whatsapp_url'])
                            <span>Si tu distrito no esta disponible, consulta otras opciones por
                                <a href="{{ $delivery['whatsapp_url'] }}" target="_blank" rel="noopener noreferrer">WhatsApp</a>.
                            </span>
                        @endif
                    </div>
                </div>

                <x-checkout.contact-address-form
                    :checkout-form="$checkoutForm"
                    :delivery="$delivery"
                    :warnings="$checkout['warnings']"
                />
            </div>

            <div class="checkout-stage" data-checkout-stage="2" @if($activeStep !== 2) hidden @endif>
                <x-checkout.fiscal-review-form
                    :checkout-form="$checkoutForm"
                    :warnings="$checkout['warnings']"
                />
            </div>

            <div class="checkout-stage" data-checkout-stage="3" @if($activeStep !== 3) hidden @endif>
                <section class="checkout-card checkout-payment-stage p-4 p-lg-5" aria-labelledby="checkout-payment-title">
                    <span class="checkout-payment-icon"><i class="bi bi-check-lg" aria-hidden="true"></i></span>
                    <h2 class="h4 fw-black mt-3 mb-2" id="checkout-payment-title">Pago</h2>
                    <p class="mb-1">Los datos y el total de tu compra fueron revisados correctamente.</p>
                    <p class="small text-muted mb-0">Todavia no se genero ningun pedido ni se realizo ningun cobro.</p>

                    <div class="checkout-stage-actions mt-4">
                        <a class="btn btn-vn-outline" href="{{ route('checkout.index', ['paso' => 2]) }}">
                            <i class="bi bi-arrow-left me-1" aria-hidden="true"></i>
                            Volver al comprobante
                        </a>
                        <form
                            method="POST"
                            action="{{ route('checkout.confirm') }}"
                            data-checkout-revalidation-form
                            data-checkout-revalidation-url="{{ route('checkout.confirm') }}"
                        >
                            @csrf
                            <input
                                type="hidden"
                                name="review_reference"
                                value="{{ data_get($checkoutForm, 'review.reference') }}"
                                data-checkout-review-reference
                            >
                            <input
                                type="hidden"
                                name="idempotency_key"
                                value="{{ $checkoutForm['confirmation_attempt_key'] }}"
                                data-checkout-idempotency-key
                            >
                            <button class="btn btn-vn" type="submit" data-checkout-revalidation-submit>
                                <span>Confirmar pedido y pagar</span>
                                <i class="bi bi-arrow-right ms-1" aria-hidden="true"></i>
                            </button>
                        </form>
                    </div>
                </section>
            </div>
        </div>
    </div>
</section>

<x-checkout.revalidation-modal />
@endsection
