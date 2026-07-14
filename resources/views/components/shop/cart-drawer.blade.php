<div
    class="offcanvas offcanvas-end cart-drawer"
    tabindex="-1"
    id="cartDrawer"
    aria-labelledby="cartDrawerLabel"
    data-cart-drawer
    data-cart-info-url="{{ route('shop.cart.info') }}"
    data-cart-warnings-clear-url="{{ route('shop.cart.warnings.clear') }}"
>
    <div class="offcanvas-header border-bottom">
        <div>
            <h2 class="offcanvas-title h5 fw-black" id="cartDrawerLabel">Tu carrito</h2>
            <div class="small text-muted"><span data-cart-drawer-count>{{ $cartCount }}</span> unidad(es)</div>
        </div>
        <button type="button" class="btn-close" data-bs-dismiss="offcanvas" aria-label="Cerrar"></button>
    </div>

    <div class="offcanvas-body d-flex flex-column">
        <div class="alert alert-warning small d-none" data-cart-drawer-warnings>
            <div class="d-flex align-items-start justify-content-between gap-2">
                <div data-cart-warnings-list></div>
                <button class="btn-close flex-shrink-0" type="button" data-cart-warnings-clear aria-label="Cerrar aviso"></button>
            </div>
        </div>

        <div class="cart-drawer-empty text-center py-5 d-none" data-cart-drawer-empty>
            <i class="bi bi-cart3 display-6 text-vn-green"></i>
            <h3 class="h5 fw-black mt-3">Tu carrito esta vacio</h3>
            <p class="text-muted mb-4">Agrega productos para verlos aqui.</p>
            <a class="btn btn-vn" href="{{ route('shop.catalog') }}">Ver catalogo</a>
        </div>

        <div class="d-flex flex-column flex-grow-1 min-h-0" data-cart-drawer-filled>
            <div class="cart-drawer-items d-grid gap-3" data-cart-drawer-items></div>

            <div class="cart-drawer-summary border-top mt-auto pt-3">
                <div class="d-flex justify-content-between small mb-2">
                    <span>Subtotal</span>
                    <strong data-cart-drawer-subtotal>S/ 0.00</strong>
                </div>
                <div class="d-flex justify-content-between fs-5 mb-3">
                    <span>Total</span>
                    <strong data-cart-drawer-total>S/ 0.00</strong>
                </div>
                <div class="d-grid gap-2">
                    <a class="btn btn-vn" href="{{ route('checkout.index') }}">Proceder al checkout</a>
                    <a class="btn btn-vn-outline" href="{{ route('shop.cart') }}">Ver carrito</a>
                </div>
            </div>
        </div>
    </div>
</div>
