@props(['checkoutForm', 'delivery'])

@php
    $checkoutErrors = $errors->getBag('checkout');
    $selectedMethod = old('delivery_method', $delivery['selected_method']);
    $homeDeliverySelected = $selectedMethod === 'home_delivery';
    $defaultChoice = $checkoutForm['selected_address_id']
        ? 'address:'.$checkoutForm['selected_address_id']
        : 'new';
    $selectedChoice = old('address_choice', $defaultChoice);
    $newAddressSelected = $homeDeliverySelected && $selectedChoice === 'new';
    $contactName = old('contact_name', $checkoutForm['contact']['name']);
    $contactPhone = old('contact_phone', $checkoutForm['contact']['phone']);
    $selectedProvince = old('province_code', '');
    $selectedDistrict = old('district_code', '');
    $selectedLocation = $selectedProvince !== ''
        ? ($checkoutForm['location_catalog'][$selectedProvince] ?? null)
        : null;
    $department = $selectedLocation['department'] ?? '';
    $isFirstAddress = $checkoutForm['is_first_address'];
    $isDefault = $isFirstAddress || (bool) old('is_default', false);
    $initialQuoteReference = (string) data_get($delivery, 'quote.quote_reference', '');
@endphp

<form
    class="checkout-contact-form d-grid gap-4"
    method="POST"
    action="{{ route('checkout.contact-address.store') }}"
    data-address-form
    data-checkout-contact-address-form
    data-checkout-delivery
    data-checkout-quote-url="{{ route('checkout.delivery.quote') }}"
    data-initial-quote="{{ $initialQuoteReference !== '' && ! session()->hasOldInput() ? '1' : '0' }}"
    data-selected-district="{{ $selectedDistrict }}"
    aria-busy="false"
>
    @csrf
    <input
        type="hidden"
        name="quote_reference"
        value="{{ $initialQuoteReference }}"
        data-checkout-quote-reference
    >

    <script type="application/json" data-address-location-catalog>@json($checkoutForm['location_catalog'])</script>
    <script type="application/json" data-checkout-base-summary>@json($delivery['base_summary'])</script>

    <section class="checkout-card p-3 p-lg-4" aria-labelledby="checkout-contact-title">
        <div class="checkout-section-heading">
            <span class="checkout-step-number"><i class="bi bi-person" aria-hidden="true"></i></span>
            <div>
                <h2 class="h5 fw-black mb-1" id="checkout-contact-title">Datos de contacto</h2>
                <p class="small text-muted mb-0">Estos datos se usaran solo para esta compra y no modificaran tu perfil.</p>
            </div>
        </div>

        <div class="row g-3 mt-1">
            <div class="col-md-6">
                <label class="form-label" for="checkout-contact-name">Nombre completo <span class="required-mark">*</span></label>
                <input
                    class="form-control {{ $checkoutErrors->has('contact_name') ? 'is-invalid' : '' }}"
                    id="checkout-contact-name"
                    name="contact_name"
                    type="text"
                    maxlength="120"
                    value="{{ $contactName }}"
                    autocomplete="name"
                    required
                >
                @if($checkoutErrors->has('contact_name'))
                    <div class="invalid-feedback">{{ $checkoutErrors->first('contact_name') }}</div>
                @endif
            </div>
            <div class="col-md-6">
                <label class="form-label" for="checkout-contact-email">Correo verificado</label>
                <div class="input-group">
                    <span class="input-group-text text-vn-green"><i class="bi bi-patch-check-fill" aria-hidden="true"></i></span>
                    <input
                        class="form-control"
                        id="checkout-contact-email"
                        name="contact_email"
                        type="email"
                        value="{{ $checkoutForm['contact']['email'] }}"
                        readonly
                        aria-describedby="checkout-contact-email-help"
                    >
                </div>
                <div class="form-text" id="checkout-contact-email-help">Las confirmaciones se enviaran a este correo.</div>
            </div>
            <div class="col-md-6">
                <label class="form-label" for="checkout-contact-phone">Celular de contacto <span class="required-mark">*</span></label>
                <input
                    class="form-control {{ $checkoutErrors->has('contact_phone') ? 'is-invalid' : '' }}"
                    id="checkout-contact-phone"
                    name="contact_phone"
                    type="tel"
                    inputmode="numeric"
                    maxlength="9"
                    pattern="9[0-9]{8}"
                    value="{{ $contactPhone }}"
                    autocomplete="tel"
                    placeholder="987654321"
                    required
                >
                @if($checkoutErrors->has('contact_phone'))
                    <div class="invalid-feedback">{{ $checkoutErrors->first('contact_phone') }}</div>
                @endif
            </div>
        </div>
    </section>

    <x-checkout.shipping-form :delivery="$delivery" />

    <section
        class="checkout-card p-3 p-lg-4 {{ $homeDeliverySelected ? '' : 'd-none' }}"
        aria-labelledby="checkout-address-title"
        data-checkout-address-section
    >
        <div class="checkout-section-heading">
            <span class="checkout-step-number"><i class="bi bi-geo-alt" aria-hidden="true"></i></span>
            <div class="flex-grow-1">
                <div class="d-flex flex-wrap align-items-start justify-content-between gap-2">
                    <div>
                        <h2 class="h5 fw-black mb-1" id="checkout-address-title">Direccion de entrega</h2>
                        <p class="small text-muted mb-0">Selecciona una direccion guardada o registra una nueva.</p>
                    </div>
                    <a class="small fw-bold" href="{{ route('account.addresses') }}">Administrar direcciones</a>
                </div>
            </div>
        </div>

        @if($checkoutErrors->has('address_choice') || $checkoutErrors->has('address_id'))
            <div class="alert alert-danger py-2 mt-3 mb-0" role="alert" data-checkout-error>
                {{ $checkoutErrors->first('address_choice') ?: $checkoutErrors->first('address_id') }}
            </div>
        @endif

        @if($checkoutForm['addresses'])
            <div class="checkout-address-list mt-3">
                @foreach($checkoutForm['addresses'] as $address)
                    @php($choice = 'address:'.$address['id'])
                    <label class="checkout-address-option">
                        <input
                            class="form-check-input"
                            name="address_choice"
                            type="radio"
                            value="{{ $choice }}"
                            {{ $selectedChoice === $choice ? 'checked' : '' }}
                            data-checkout-address-choice
                            data-address-id="{{ $address['id'] }}"
                            data-delivery-available="{{ $address['delivery_available'] ? '1' : '0' }}"
                            @disabled(! $address['delivery_available'])
                            @required($homeDeliverySelected && $address['delivery_available'])
                        >
                        <span class="checkout-address-option-content">
                            <span class="d-flex flex-wrap align-items-center gap-2 mb-1">
                                <strong>{{ $address['label'] }}</strong>
                                @if($address['is_default'])
                                    <span class="badge text-bg-success">Predeterminada</span>
                                @endif
                                @if($address['delivery_available'])
                                    <span class="badge checkout-coverage-badge">Tarifa base {{ $address['formatted_shipping_fee'] }}</span>
                                @else
                                    <span class="badge text-bg-warning">No disponible</span>
                                @endif
                            </span>
                            <span class="d-block">{{ $address['recipient_name'] }} · {{ $address['phone'] }}</span>
                            <span class="d-block text-muted small">{{ $address['address_line'] }}, {{ $address['district'] }}, {{ $address['province'] }}</span>
                            @if($address['reference'])
                                <span class="d-block text-muted small">Referencia: {{ $address['reference'] }}</span>
                            @endif
                        </span>
                    </label>
                @endforeach
            </div>
        @endif

        @if($checkoutForm['can_create_address'])
            <label class="checkout-address-option checkout-address-new-option {{ $checkoutForm['addresses'] ? '' : 'mt-3' }}">
                <input
                    class="form-check-input"
                    name="address_choice"
                    type="radio"
                    value="new"
                    {{ $newAddressSelected ? 'checked' : '' }}
                    data-checkout-address-choice
                    data-delivery-available="1"
                    @required($homeDeliverySelected)
                >
                <span class="checkout-address-option-content">
                    <strong><i class="bi bi-plus-circle me-1" aria-hidden="true"></i>Agregar una nueva direccion</strong>
                    <span class="d-block text-muted small">Se guardara en tu cuenta para esta y futuras compras.</span>
                </span>
            </label>
        @else
            <div class="alert alert-warning mt-3 mb-0" role="alert">
                <strong>Guardaste {{ $checkoutForm['address_count'] }} de {{ $checkoutForm['address_limit'] }} direcciones.</strong>
                Selecciona una direccion existente o elimina una desde
                <a class="alert-link" href="{{ route('account.addresses') }}">Mis direcciones</a>.
            </div>
        @endif

        @if($checkoutForm['can_create_address'])
            <div class="checkout-new-address-panel mt-4 {{ $newAddressSelected ? '' : 'd-none' }}" data-checkout-new-address>
                <h3 class="h6 fw-black mb-3">Nueva direccion</h3>

                <div class="row g-3">
                    <div class="col-md-6">
                        <label class="form-label" for="checkout-address-label">Etiqueta <span class="required-mark">*</span></label>
                        <input
                            class="form-control {{ $checkoutErrors->has('label') ? 'is-invalid' : '' }}"
                            id="checkout-address-label"
                            name="label"
                            type="text"
                            maxlength="50"
                            value="{{ old('label') }}"
                            placeholder="Ej. Casa"
                            required
                            @disabled(! $newAddressSelected)
                        >
                        @if($checkoutErrors->has('label'))
                            <div class="invalid-feedback">{{ $checkoutErrors->first('label') }}</div>
                        @endif
                    </div>
                    <div class="col-md-6">
                        <label class="form-label" for="checkout-address-recipient">Persona que recibe <span class="required-mark">*</span></label>
                        <input
                            class="form-control {{ $checkoutErrors->has('recipient_name') ? 'is-invalid' : '' }}"
                            id="checkout-address-recipient"
                            name="recipient_name"
                            type="text"
                            maxlength="120"
                            value="{{ old('recipient_name', $contactName) }}"
                            required
                            @disabled(! $newAddressSelected)
                        >
                        @if($checkoutErrors->has('recipient_name'))
                            <div class="invalid-feedback">{{ $checkoutErrors->first('recipient_name') }}</div>
                        @endif
                    </div>
                    <div class="col-md-6">
                        <label class="form-label" for="checkout-address-phone">Celular para la entrega <span class="required-mark">*</span></label>
                        <input
                            class="form-control {{ $checkoutErrors->has('phone') ? 'is-invalid' : '' }}"
                            id="checkout-address-phone"
                            name="phone"
                            type="tel"
                            inputmode="numeric"
                            maxlength="9"
                            pattern="9[0-9]{8}"
                            value="{{ old('phone', $contactPhone) }}"
                            placeholder="987654321"
                            required
                            @disabled(! $newAddressSelected)
                        >
                        @if($checkoutErrors->has('phone'))
                            <div class="invalid-feedback">{{ $checkoutErrors->first('phone') }}</div>
                        @endif
                    </div>
                    <div class="col-md-6">
                        <label class="form-label" for="checkout-address-department">Departamento</label>
                        <input
                            class="form-control"
                            id="checkout-address-department"
                            type="text"
                            value="{{ $department }}"
                            placeholder="Se completara automaticamente"
                            readonly
                            data-address-department
                            @disabled(! $newAddressSelected)
                        >
                    </div>
                    <div class="col-md-6">
                        <label class="form-label" for="checkout-address-province">Provincia <span class="required-mark">*</span></label>
                        <select
                            class="form-select {{ $checkoutErrors->has('province_code') ? 'is-invalid' : '' }}"
                            id="checkout-address-province"
                            name="province_code"
                            required
                            data-address-province
                            @disabled(! $newAddressSelected)
                        >
                            <option value="">Selecciona una provincia</option>
                            @foreach($checkoutForm['location_catalog'] as $provinceCode => $province)
                                <option value="{{ $provinceCode }}" {{ (string) $selectedProvince === (string) $provinceCode ? 'selected' : '' }}>{{ $province['name'] }}</option>
                            @endforeach
                        </select>
                        @if($checkoutErrors->has('province_code'))
                            <div class="invalid-feedback">{{ $checkoutErrors->first('province_code') }}</div>
                        @endif
                    </div>
                    <div class="col-md-6">
                        <label class="form-label" for="checkout-address-district">Distrito <span class="required-mark">*</span></label>
                        <select
                            class="form-select {{ $checkoutErrors->has('district_code') ? 'is-invalid' : '' }}"
                            id="checkout-address-district"
                            name="district_code"
                            required
                            data-address-district
                            @disabled(! $newAddressSelected)
                        >
                            <option value="">Selecciona primero una provincia</option>
                            @if($selectedLocation)
                                @foreach($selectedLocation['districts'] as $district)
                                    <option value="{{ $district['code'] }}" {{ (string) $selectedDistrict === (string) $district['code'] ? 'selected' : '' }}>{{ $district['name'] }}</option>
                                @endforeach
                            @endif
                        </select>
                        @if($checkoutErrors->has('district_code'))
                            <div class="invalid-feedback">{{ $checkoutErrors->first('district_code') }}</div>
                        @endif
                    </div>
                    <div class="col-md-6">
                        <label class="form-label" for="checkout-address-ubigeo">UBIGEO</label>
                        <input
                            class="form-control"
                            id="checkout-address-ubigeo"
                            type="text"
                            value="{{ $selectedDistrict }}"
                            placeholder="Se completara automaticamente"
                            readonly
                            data-address-ubigeo
                            @disabled(! $newAddressSelected)
                        >
                    </div>
                    <div class="col-12">
                        <label class="form-label" for="checkout-address-line">Direccion <span class="required-mark">*</span></label>
                        <input
                            class="form-control {{ $checkoutErrors->has('address_line') ? 'is-invalid' : '' }}"
                            id="checkout-address-line"
                            name="address_line"
                            type="text"
                            maxlength="255"
                            value="{{ old('address_line') }}"
                            placeholder="Ej. Av. Caminos del Inca 1234, dpto. 502"
                            required
                            @disabled(! $newAddressSelected)
                        >
                        @if($checkoutErrors->has('address_line'))
                            <div class="invalid-feedback">{{ $checkoutErrors->first('address_line') }}</div>
                        @endif
                    </div>
                    <div class="col-12">
                        <label class="form-label" for="checkout-address-reference">Referencia</label>
                        <textarea
                            class="form-control {{ $checkoutErrors->has('reference') ? 'is-invalid' : '' }}"
                            id="checkout-address-reference"
                            name="reference"
                            rows="2"
                            maxlength="255"
                            placeholder="Ej. Frente al parque, puerta verde"
                            @disabled(! $newAddressSelected)
                        >{{ old('reference') }}</textarea>
                        @if($checkoutErrors->has('reference'))
                            <div class="invalid-feedback">{{ $checkoutErrors->first('reference') }}</div>
                        @endif
                    </div>
                </div>

                <div class="checkout-default-setting mt-3">
                    <input type="hidden" name="is_default" value="{{ $isFirstAddress ? '1' : '0' }}" @disabled(! $newAddressSelected)>
                    <div class="form-check">
                        <input
                            class="form-check-input"
                            id="checkout-address-default"
                            name="is_default"
                            type="checkbox"
                            value="1"
                            {{ $isDefault ? 'checked' : '' }}
                            {{ $isFirstAddress ? 'disabled' : '' }}
                            data-checkout-new-address-input
                            data-locked="{{ $isFirstAddress ? '1' : '0' }}"
                            @disabled(! $newAddressSelected || $isFirstAddress)
                        >
                        <label class="form-check-label fw-bold" for="checkout-address-default">Usar como direccion predeterminada</label>
                    </div>
                    <p class="small text-muted mb-0 mt-1">
                        {{ $isFirstAddress ? 'Tu primera direccion sera predeterminada automaticamente.' : 'La seleccionaremos primero en futuras compras.' }}
                    </p>
                </div>
            </div>
        @endif

    </section>

    <div class="d-flex flex-column flex-sm-row justify-content-end gap-2">
        <button class="btn btn-vn" type="submit" data-checkout-contact-submit>
            <span>Continuar al comprobante</span>
            <i class="bi bi-arrow-right ms-1" aria-hidden="true"></i>
        </button>
    </div>
</form>
