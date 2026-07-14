<div class="modal fade" id="cartQuantityModal" tabindex="-1" aria-labelledby="cartQuantityModalLabel" aria-hidden="true" data-cart-quantity-modal>
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <h2 class="modal-title h5 fw-black" id="cartQuantityModalLabel">Agregar al carrito</h2>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Cerrar"></button>
            </div>
            <div class="modal-body">
                <div class="d-flex gap-3 align-items-center mb-3">
                    <div class="thumb-sm flex-shrink-0" data-cart-modal-image></div>
                    <div class="min-w-0">
                        <h3 class="h6 fw-black mb-1" data-cart-modal-name>Producto natural</h3>
                        <div class="price fs-6" data-cart-modal-price>S/ 0.00</div>
                    </div>
                </div>

                <div class="d-flex align-items-center justify-content-between gap-3" data-cart-form>
                    <div>
                        <label class="form-label fw-bold mb-1" for="cartQuantityModalInput">Cantidad</label>
                        <div class="small text-muted" data-cart-modal-stock-label>Disponible: 1 unidad</div>
                    </div>
                    <div class="quantity-control">
                        <button data-quantity="minus" type="button">-</button>
                        <input id="cartQuantityModalInput" type="number" value="1" min="1" max="1" data-cart-quantity>
                        <button data-quantity="plus" type="button">+</button>
                    </div>
                </div>
            </div>
            <div class="modal-footer">
                <button class="btn btn-vn-outline" type="button" data-bs-dismiss="modal">Cancelar</button>
                <button class="btn btn-vn" type="button" data-cart-add data-cart-modal-submit>
                    <i class="bi bi-cart-plus me-1"></i>Anadir al carrito
                </button>
            </div>
        </div>
    </div>
</div>
