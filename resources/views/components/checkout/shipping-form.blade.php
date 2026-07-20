@props(['delivery'])

@php
    $checkoutErrors = $errors->getBag('checkout');
    $selectedMethod = old('delivery_method', $delivery['selected_method']);
    $quote = $delivery['quote'];
    $message = $quote['message'] ?? $delivery['unavailable_message'] ?? 'Selecciona una modalidad para calcular el total de tu compra.';
    $messageStyle = $quote ? 'success' : ($delivery['unavailable_message'] ? 'warning' : 'secondary');
@endphp

<fieldset class="checkout-card p-3 p-lg-4" data-checkout-delivery-section>
    <legend class="visually-hidden">Modalidad de entrega</legend>

    <div class="checkout-section-heading">
        <span class="checkout-step-number"><i class="bi bi-truck" aria-hidden="true"></i></span>
        <div>
            <h2 class="h5 fw-black mb-1">Modalidad de entrega</h2>
            <p class="small text-muted mb-0">Elige como deseas recibir tu compra.</p>
        </div>
    </div>

    @if($checkoutErrors->has('delivery_method'))
        <div class="alert alert-danger py-2 mt-3 mb-0" role="alert">
            {{ $checkoutErrors->first('delivery_method') }}
        </div>
    @endif

    <div class="checkout-delivery-options mt-3">
        <label class="checkout-delivery-option">
            <input
                class="form-check-input"
                name="delivery_method"
                type="radio"
                value="home_delivery"
                {{ $selectedMethod === 'home_delivery' ? 'checked' : '' }}
                data-checkout-delivery-method
                required
            >
            <span class="checkout-delivery-option-icon"><i class="bi bi-truck" aria-hidden="true"></i></span>
            <span class="checkout-delivery-option-content">
                <strong>Entrega a domicilio</strong>
                <span>Lima Metropolitana y Callao</span>
            </span>
        </label>

        @if($delivery['pickup_available'])
            <label class="checkout-delivery-option">
                <input
                    class="form-check-input"
                    name="delivery_method"
                    type="radio"
                    value="pickup"
                    {{ $selectedMethod === 'pickup' ? 'checked' : '' }}
                    data-checkout-delivery-method
                    required
                >
                <span class="checkout-delivery-option-icon"><i class="bi bi-shop" aria-hidden="true"></i></span>
                <span class="checkout-delivery-option-content">
                    <strong>Recojo en tienda</strong>
                    <span>Sin costo</span>
                </span>
            </label>
        @endif
    </div>

    <div
        class="alert alert-{{ $messageStyle }} checkout-delivery-feedback mt-3 mb-0"
        data-checkout-delivery-feedback
        data-whatsapp-url="{{ $delivery['whatsapp_url'] }}"
        role="status"
        aria-live="polite"
        tabindex="-1"
    >
        <span class="checkout-delivery-feedback-icon" data-checkout-delivery-feedback-icon>
            <i class="bi {{ $quote ? 'bi-check-circle-fill' : ($delivery['unavailable_message'] ? 'bi-exclamation-triangle-fill' : 'bi-info-circle-fill') }}" aria-hidden="true"></i>
        </span>
        <span data-checkout-delivery-feedback-message>{{ $message }}</span>
        @if($delivery['whatsapp_url'])
            <a
                class="alert-link {{ $delivery['unavailable_message'] ? '' : 'd-none' }}"
                href="{{ $delivery['whatsapp_url'] }}"
                target="_blank"
                rel="noopener noreferrer"
                data-checkout-delivery-whatsapp
            >Contactar por WhatsApp</a>
        @endif
    </div>

    @if($delivery['pickup_available'])
        <div class="checkout-pickup-details mt-3 {{ $selectedMethod === 'pickup' ? '' : 'd-none' }}" data-checkout-pickup-details>
            <div class="d-flex align-items-start gap-3">
                <i class="bi bi-geo-alt-fill text-vn-green fs-5" aria-hidden="true"></i>
                <div>
                    <strong class="d-block">Punto de recojo</strong>
                    <span class="d-block" data-checkout-pickup-address>{{ $delivery['pickup_address'] }}</span>
                    <span class="d-block small text-muted mt-1">
                        Tu pedido estara disponible para recojo <span data-checkout-pickup-window>{{ $delivery['pickup_availability_label'] }}</span>. Te avisaremos apenas este listo.
                        Tendras <span data-checkout-pickup-hold-days>{{ $delivery['pickup_hold_days'] }}</span> dias calendario para recogerlo desde que te avisemos que esta listo.
                    </span>
                </div>
            </div>
        </div>
    @endif
</fieldset>
