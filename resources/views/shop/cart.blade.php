@extends('layouts.shop')

@section('title', 'Carrito | VitaNatural')

@section('content')
@php
    $cartData = $cart->toArray();
@endphp

<section class="container py-5">
    <div class="d-flex flex-wrap align-items-end justify-content-between gap-3 mb-4">
        <div>
            <h1 class="section-title mb-1">Tu carrito de compras</h1>
            <p class="text-muted mb-0">Revisa productos, cantidades y subtotales antes de continuar.</p>
        </div>
        <a class="btn btn-vn-outline" href="{{ route('shop.catalog') }}">
            <i class="bi bi-arrow-left me-1"></i>Seguir comprando
        </a>
    </div>

    <div
        data-cart-page
        data-cart-info-url="{{ route('shop.cart.info') }}"
        data-cart-clear-url="{{ route('shop.cart.clear') }}"
        data-cart-warnings-clear-url="{{ route('shop.cart.warnings.clear') }}"
    >
        <div class="alert alert-warning {{ $cart->warnings ? '' : 'd-none' }}" data-cart-warnings>
            <div class="d-flex align-items-start justify-content-between gap-3">
                <div data-cart-warnings-list>
                    @foreach($cart->warnings as $warning)
                        <div>{{ $warning }}</div>
                    @endforeach
                </div>
                <button class="btn-close flex-shrink-0" type="button" data-cart-warnings-clear aria-label="Cerrar aviso"></button>
            </div>
        </div>

        <div class="row g-4 {{ $cart->isEmpty() ? 'd-none' : '' }}" data-cart-filled>
            <div class="col-lg-8">
                <div class="checkout-card p-3">
                    <div class="table-responsive">
                        <table class="table align-middle mb-0 cart-table">
                            <thead>
                                <tr>
                                    <th>Producto</th>
                                    <th>Precio</th>
                                    <th>Cantidad</th>
                                    <th>Subtotal</th>
                                    <th></th>
                                </tr>
                            </thead>
                            <tbody data-cart-page-items>
                                @foreach($cartData['items'] as $item)
                                    <tr data-cart-page-item data-product-id="{{ $item['product_id'] }}">
                                        <td>
                                            <div class="d-flex align-items-center gap-3">
                                                <a class="thumb-sm flex-shrink-0" href="{{ $item['url'] }}" style="background-image: url('{{ $item['image_url'] }}')"></a>
                                                <div>
                                                    <a class="fw-bold" href="{{ $item['url'] }}">{{ $item['name'] }}</a><br>
                                                    <span class="small text-muted">{{ $item['description'] ?: 'Producto natural' }}</span>
                                                </div>
                                            </div>
                                        </td>
                                        <td><strong>{{ $item['formatted_unit_price'] }}</strong></td>
                                        <td>
                                            <div class="quantity-control">
                                                <button data-quantity="minus" data-cart-update-button type="button">-</button>
                                                <input
                                                    type="number"
                                                    value="{{ $item['quantity'] }}"
                                                    min="1"
                                                    max="{{ max(1, $item['stock']) }}"
                                                    data-cart-page-quantity
                                                    data-cart-update-url="{{ $item['update_url'] }}"
                                                >
                                                <button data-quantity="plus" data-cart-update-button type="button">+</button>
                                            </div>
                                        </td>
                                        <td><strong>{{ $item['formatted_subtotal'] }}</strong></td>
                                        <td>
                                            <button class="btn btn-link text-muted" type="button" data-cart-remove data-cart-remove-url="{{ $item['remove_url'] }}" aria-label="Eliminar {{ $item['name'] }}">
                                                <i class="bi bi-trash"></i>
                                            </button>
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                </div>

                <div class="checkout-card p-3 mt-4">
                    <label class="form-label fw-bold">Tienes un cupon de descuento?</label>
                    <div class="input-group" style="max-width: 420px;">
                        <input class="form-control" type="text" placeholder="Ingresa tu cupon" disabled>
                        <button class="btn btn-vn-outline" type="button" disabled>Aplicar</button>
                    </div>
                    <div class="small text-muted mt-2">Los cupones se habilitaran en un siguiente sprint.</div>
                </div>
            </div>
            <div class="col-lg-4">
                <aside class="checkout-card p-3 p-lg-4">
                    <h5 class="fw-black mb-3">Resumen del pedido</h5>
                    <div class="d-grid gap-2 small">
                        <div class="d-flex justify-content-between">
                            <span>Productos</span>
                            <strong data-cart-summary-products>{{ $cartData['product_count'] }} ({{ $cartData['total_quantity'] }} unidades)</strong>
                        </div>
                        <div class="d-flex justify-content-between"><span>Subtotal</span><strong data-cart-summary-subtotal>{{ $cartData['formatted_subtotal'] }}</strong></div>
                        <div class="d-flex justify-content-between"><span>Envio</span><strong>Se calcula en checkout</strong></div>
                        <div class="d-flex justify-content-between"><span>Descuento</span><strong>S/ 0.00</strong></div>
                        <hr>
                        <div class="d-flex justify-content-between fs-5"><span>Total</span><strong data-cart-summary-total>{{ $cartData['formatted_total'] }}</strong></div>
                        <span class="text-muted text-end">Incluye IGV</span>
                    </div>
                    <a class="btn btn-vn w-100 mt-4" href="{{ route('checkout.index') }}">Proceder al checkout</a>
                    <button
                        class="btn btn-vn-outline w-100 mt-2"
                        type="button"
                        data-bs-toggle="modal"
                        data-bs-target="#clearCartModal"
                    >
                        Vaciar carrito
                    </button>
                </aside>
            </div>
        </div>

        <div class="checkout-card p-5 text-center {{ $cart->isEmpty() ? '' : 'd-none' }}" data-cart-empty>
            <i class="bi bi-cart3 display-5 text-vn-green"></i>
            <h2 class="h4 fw-black mt-3">Tu carrito esta vacio</h2>
            <p class="text-muted mb-4">Agrega productos naturales para verlos aqui antes de comprar.</p>
            <a class="btn btn-vn" href="{{ route('shop.catalog') }}">Ver catalogo</a>
        </div>
    </div>

    <div class="mt-4">
        <x-shop.trust-badges />
    </div>
</section>

<div class="modal fade" id="clearCartModal" tabindex="-1" aria-labelledby="clearCartModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <h2 class="modal-title h5 fw-black" id="clearCartModalLabel">Vaciar carrito</h2>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Cerrar"></button>
            </div>
            <div class="modal-body">
                <p class="mb-1">Estas a punto de retirar todos los productos de tu carrito.</p>
                <p class="text-muted small mb-0">Esta accion no afecta el catalogo ni tu cuenta, pero tendras que agregar los productos nuevamente si deseas comprarlos.</p>
            </div>
            <div class="modal-footer">
                <button class="btn btn-vn-outline" type="button" data-bs-dismiss="modal">Cancelar</button>
                <button class="btn btn-danger" type="button" data-cart-clear data-bs-dismiss="modal">
                    <i class="bi bi-trash me-1"></i>Vaciar carrito
                </button>
            </div>
        </div>
    </div>
</div>
@endsection
