<div class="checkout-card p-3 p-lg-4">
    <h5 class="fw-bold mb-3">2. Envio o recojo</h5>
    <div class="d-grid gap-3">
        <label class="border rounded-2 p-3">
            <input class="form-check-input me-2" name="delivery" type="radio" value="delivery" data-checkout-option checked>
            Envio a domicilio <span class="text-muted small">Lima Metropolitana desde 24 h</span>
        </label>
        <label class="border rounded-2 p-3">
            <input class="form-check-input me-2" name="delivery" type="radio" value="pickup" data-checkout-option>
            Recojo en tienda <span class="text-muted small">Sin costo</span>
        </label>
        <div data-checkout-panel="delivery">
            <label class="form-label">Direccion de entrega</label>
            <input class="form-control mb-2" type="text" placeholder="Av., calle, numero, distrito">
            <textarea class="form-control" rows="2" placeholder="Referencia para el repartidor"></textarea>
        </div>
        <div class="d-none" data-checkout-panel="pickup">
            <div class="alert alert-success mb-0">
                Puedes recoger tu pedido en Av. Camino del Inca 1234, Surco. Te avisaremos por WhatsApp.
            </div>
        </div>
    </div>
</div>
