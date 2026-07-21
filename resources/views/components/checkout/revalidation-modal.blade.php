<div
    class="modal fade"
    id="checkoutRevalidationModal"
    tabindex="-1"
    aria-labelledby="checkoutRevalidationTitle"
    aria-describedby="checkoutRevalidationMessage"
    aria-hidden="true"
    data-bs-backdrop="static"
    data-bs-keyboard="false"
    data-checkout-revalidation-modal
>
    <div class="modal-dialog modal-dialog-centered modal-dialog-scrollable">
        <div class="modal-content checkout-revalidation-modal">
            <div class="modal-header align-items-start">
                <span class="checkout-revalidation-icon" data-checkout-revalidation-icon>
                    <i class="bi bi-exclamation-lg" aria-hidden="true"></i>
                </span>
                <div class="flex-grow-1">
                    <h2 class="modal-title h5 fw-black mb-1" id="checkoutRevalidationTitle" data-checkout-revalidation-title>
                        Revisa los cambios de tu pedido
                    </h2>
                    <p class="small text-muted mb-0" id="checkoutRevalidationMessage" data-checkout-revalidation-message aria-live="polite"></p>
                </div>
            </div>

            <div class="modal-body">
                <div class="checkout-revalidation-totals" data-checkout-revalidation-totals>
                    <div>
                        <span>Antes</span>
                        <strong data-checkout-revalidation-previous-total>S/ 0.00</strong>
                    </div>
                    <i class="bi bi-arrow-right" aria-hidden="true"></i>
                    <div>
                        <span>Ahora</span>
                        <strong data-checkout-revalidation-current-total>S/ 0.00</strong>
                    </div>
                </div>

                <div class="mt-4" data-checkout-revalidation-changes-wrapper>
                    <h3 class="h6 fw-black mb-2">Que cambio</h3>
                    <ul class="checkout-revalidation-changes mb-0" data-checkout-revalidation-changes></ul>
                </div>

                <div class="alert alert-light border small mt-3 mb-0 d-none" role="note" data-checkout-revalidation-preserved></div>
            </div>

            <div class="modal-footer checkout-revalidation-actions">
                <a class="btn btn-vn-outline" href="{{ route('shop.cart') }}" data-checkout-revalidation-back>
                    <i class="bi bi-arrow-left me-1" aria-hidden="true"></i>
                    Volver al carrito
                </a>
                <a class="btn btn-vn d-none" href="{{ route('checkout.index', ['paso' => 2]) }}" data-checkout-revalidation-review>
                    Revisar y continuar
                </a>
                <button class="btn btn-vn" type="button" data-checkout-revalidation-accept>
                    <span>Aceptar cambios y continuar</span>
                    <i class="bi bi-arrow-right ms-1" aria-hidden="true"></i>
                </button>
            </div>
        </div>
    </div>
</div>
