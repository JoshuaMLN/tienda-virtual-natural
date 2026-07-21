@props(['checkoutForm', 'warnings' => []])

@php
    $reviewErrors = $errors->getBag('checkoutReview');
    $fiscal = $checkoutForm['fiscal'] ?? [];
    $selectedType = old('fiscal_document_type', $fiscal['document_type'] ?? 'receipt');
    $receiptSelected = $selectedType === 'receipt';
    $receiptIdentity = old(
        'receipt_identity_document_type',
        ($fiscal['document_type'] ?? null) === 'receipt'
            ? ($fiscal['identity_document_type'] ?? 'dni')
            : 'dni',
    );
    $receiptNumber = old(
        'receipt_identity_document_number',
        ($fiscal['document_type'] ?? null) === 'receipt'
            ? ($fiscal['identity_document_number'] ?? '')
            : '',
    );
    $invoiceRuc = old(
        'invoice_ruc',
        ($fiscal['document_type'] ?? null) === 'invoice'
            ? ($fiscal['identity_document_number'] ?? '')
            : '',
    );
    $canReview = $checkoutForm['has_saved_delivery'] && $checkoutForm['terms'] !== null;
    $termsAccepted = (bool) old('terms_accepted', $checkoutForm['is_reviewed']);
@endphp

<form
    class="checkout-fiscal-form d-grid gap-4"
    method="POST"
    action="{{ route('checkout.review') }}"
    data-checkout-fiscal-form
    aria-busy="false"
>
    @csrf

    <section class="checkout-card p-3 p-lg-4" aria-labelledby="checkout-fiscal-title">
        <div class="checkout-section-heading">
            <span class="checkout-step-number"><i class="bi bi-receipt" aria-hidden="true"></i></span>
            <div>
                <h2 class="h5 fw-black mb-1" id="checkout-fiscal-title">Comprobante de pago</h2>
                <p class="small text-muted mb-0">Estos datos se usaran solo para emitir el comprobante de esta compra.</p>
            </div>
        </div>

        @if($reviewErrors->has('fiscal_document_type'))
            <div class="alert alert-danger py-2 mt-3 mb-0" role="alert" data-checkout-error>
                {{ $reviewErrors->first('fiscal_document_type') }}
            </div>
        @endif

        <fieldset class="mt-3">
            <legend class="form-label fw-bold">Tipo de comprobante <span class="required-mark">*</span></legend>
            <div class="checkout-fiscal-types">
                <label class="checkout-fiscal-type">
                    <input
                        name="fiscal_document_type"
                        type="radio"
                        value="receipt"
                        {{ $receiptSelected ? 'checked' : '' }}
                        data-checkout-fiscal-type
                        required
                    >
                    <span><i class="bi bi-receipt" aria-hidden="true"></i>Boleta</span>
                </label>
                <label class="checkout-fiscal-type">
                    <input
                        name="fiscal_document_type"
                        type="radio"
                        value="invoice"
                        {{ $selectedType === 'invoice' ? 'checked' : '' }}
                        data-checkout-fiscal-type
                        required
                    >
                    <span><i class="bi bi-building" aria-hidden="true"></i>Factura</span>
                </label>
            </div>
        </fieldset>

        <div class="checkout-fiscal-panel mt-4 {{ $receiptSelected ? '' : 'd-none' }}" data-checkout-fiscal-panel="receipt">
            <div class="row g-3">
                <div class="col-md-5">
                    <label class="form-label" for="checkout-receipt-document-type">Documento personal <span class="required-mark">*</span></label>
                    <select
                        class="form-select {{ $reviewErrors->has('receipt_identity_document_type') ? 'is-invalid' : '' }}"
                        id="checkout-receipt-document-type"
                        name="receipt_identity_document_type"
                        required
                        data-checkout-fiscal-input
                        @disabled(! $receiptSelected)
                    >
                        <option value="dni" {{ $receiptIdentity === 'dni' ? 'selected' : '' }}>DNI</option>
                        <option value="foreigner_card" {{ $receiptIdentity === 'foreigner_card' ? 'selected' : '' }}>Carnet de extranjeria</option>
                        <option value="passport" {{ $receiptIdentity === 'passport' ? 'selected' : '' }}>Pasaporte</option>
                    </select>
                    @if($reviewErrors->has('receipt_identity_document_type'))
                        <div class="invalid-feedback">{{ $reviewErrors->first('receipt_identity_document_type') }}</div>
                    @endif
                </div>
                <div class="col-md-7">
                    <label class="form-label" for="checkout-receipt-document-number">Numero de documento <span class="required-mark">*</span></label>
                    <input
                        class="form-control {{ $reviewErrors->has('receipt_identity_document_number') ? 'is-invalid' : '' }}"
                        id="checkout-receipt-document-number"
                        name="receipt_identity_document_number"
                        type="text"
                        maxlength="20"
                        value="{{ $receiptNumber }}"
                        autocomplete="off"
                        required
                        data-checkout-fiscal-input
                        @disabled(! $receiptSelected)
                    >
                    @if($reviewErrors->has('receipt_identity_document_number'))
                        <div class="invalid-feedback">{{ $reviewErrors->first('receipt_identity_document_number') }}</div>
                    @endif
                </div>
                <div class="col-md-6">
                    <label class="form-label" for="checkout-receipt-first-names">Nombres <span class="required-mark">*</span></label>
                    <input
                        class="form-control {{ $reviewErrors->has('receipt_first_names') ? 'is-invalid' : '' }}"
                        id="checkout-receipt-first-names"
                        name="receipt_first_names"
                        type="text"
                        maxlength="120"
                        value="{{ old('receipt_first_names', ($fiscal['document_type'] ?? null) === 'receipt' ? ($fiscal['first_names'] ?? '') : '') }}"
                        autocomplete="given-name"
                        required
                        data-checkout-fiscal-input
                        @disabled(! $receiptSelected)
                    >
                    @if($reviewErrors->has('receipt_first_names'))
                        <div class="invalid-feedback">{{ $reviewErrors->first('receipt_first_names') }}</div>
                    @endif
                </div>
                <div class="col-md-6">
                    <label class="form-label" for="checkout-receipt-last-names">Apellidos <span class="required-mark">*</span></label>
                    <input
                        class="form-control {{ $reviewErrors->has('receipt_last_names') ? 'is-invalid' : '' }}"
                        id="checkout-receipt-last-names"
                        name="receipt_last_names"
                        type="text"
                        maxlength="120"
                        value="{{ old('receipt_last_names', ($fiscal['document_type'] ?? null) === 'receipt' ? ($fiscal['last_names'] ?? '') : '') }}"
                        autocomplete="family-name"
                        required
                        data-checkout-fiscal-input
                        @disabled(! $receiptSelected)
                    >
                    @if($reviewErrors->has('receipt_last_names'))
                        <div class="invalid-feedback">{{ $reviewErrors->first('receipt_last_names') }}</div>
                    @endif
                </div>
                <div class="col-12">
                    <label class="form-label" for="checkout-receipt-email">Correo para la boleta <span class="required-mark">*</span></label>
                    <input
                        class="form-control {{ $reviewErrors->has('receipt_email') ? 'is-invalid' : '' }}"
                        id="checkout-receipt-email"
                        name="receipt_email"
                        type="email"
                        maxlength="255"
                        value="{{ old('receipt_email', ($fiscal['document_type'] ?? null) === 'receipt' ? ($fiscal['email'] ?? $checkoutForm['contact']['email']) : $checkoutForm['contact']['email']) }}"
                        autocomplete="email"
                        required
                        data-checkout-fiscal-input
                        @disabled(! $receiptSelected)
                    >
                    @if($reviewErrors->has('receipt_email'))
                        <div class="invalid-feedback">{{ $reviewErrors->first('receipt_email') }}</div>
                    @endif
                </div>
            </div>
        </div>

        <div class="checkout-fiscal-panel mt-4 {{ $selectedType === 'invoice' ? '' : 'd-none' }}" data-checkout-fiscal-panel="invoice">
            <div class="row g-3">
                <div class="col-md-5">
                    <label class="form-label" for="checkout-invoice-ruc">RUC <span class="required-mark">*</span></label>
                    <input
                        class="form-control {{ $reviewErrors->has('invoice_ruc') ? 'is-invalid' : '' }}"
                        id="checkout-invoice-ruc"
                        name="invoice_ruc"
                        type="text"
                        inputmode="numeric"
                        maxlength="11"
                        value="{{ $invoiceRuc }}"
                        autocomplete="off"
                        required
                        data-checkout-fiscal-input
                        @disabled($receiptSelected)
                    >
                    @if($reviewErrors->has('invoice_ruc'))
                        <div class="invalid-feedback">{{ $reviewErrors->first('invoice_ruc') }}</div>
                    @endif
                </div>
                <div class="col-md-7">
                    <label class="form-label" for="checkout-invoice-business-name">Razon social <span class="required-mark">*</span></label>
                    <input
                        class="form-control {{ $reviewErrors->has('invoice_business_name') ? 'is-invalid' : '' }}"
                        id="checkout-invoice-business-name"
                        name="invoice_business_name"
                        type="text"
                        maxlength="200"
                        value="{{ old('invoice_business_name', ($fiscal['document_type'] ?? null) === 'invoice' ? ($fiscal['business_name'] ?? '') : '') }}"
                        autocomplete="organization"
                        required
                        data-checkout-fiscal-input
                        @disabled($receiptSelected)
                    >
                    @if($reviewErrors->has('invoice_business_name'))
                        <div class="invalid-feedback">{{ $reviewErrors->first('invoice_business_name') }}</div>
                    @endif
                </div>
                <div class="col-12">
                    <label class="form-label" for="checkout-invoice-address">Domicilio fiscal <span class="required-mark">*</span></label>
                    <input
                        class="form-control {{ $reviewErrors->has('invoice_address') ? 'is-invalid' : '' }}"
                        id="checkout-invoice-address"
                        name="invoice_address"
                        type="text"
                        maxlength="255"
                        value="{{ old('invoice_address', ($fiscal['document_type'] ?? null) === 'invoice' ? ($fiscal['fiscal_address'] ?? '') : '') }}"
                        autocomplete="street-address"
                        required
                        data-checkout-fiscal-input
                        @disabled($receiptSelected)
                    >
                    @if($reviewErrors->has('invoice_address'))
                        <div class="invalid-feedback">{{ $reviewErrors->first('invoice_address') }}</div>
                    @endif
                </div>
                <div class="col-12">
                    <label class="form-label" for="checkout-invoice-email">Correo para la factura <span class="required-mark">*</span></label>
                    <input
                        class="form-control {{ $reviewErrors->has('invoice_email') ? 'is-invalid' : '' }}"
                        id="checkout-invoice-email"
                        name="invoice_email"
                        type="email"
                        maxlength="255"
                        value="{{ old('invoice_email', ($fiscal['document_type'] ?? null) === 'invoice' ? ($fiscal['email'] ?? $checkoutForm['contact']['email']) : $checkoutForm['contact']['email']) }}"
                        autocomplete="email"
                        required
                        data-checkout-fiscal-input
                        @disabled($receiptSelected)
                    >
                    @if($reviewErrors->has('invoice_email'))
                        <div class="invalid-feedback">{{ $reviewErrors->first('invoice_email') }}</div>
                    @endif
                </div>
            </div>
        </div>

    </section>

    <section class="checkout-card p-3 p-lg-4" aria-labelledby="checkout-review-title">
        <div class="checkout-section-heading">
            <span class="checkout-step-number"><i class="bi bi-file-earmark-check" aria-hidden="true"></i></span>
            <div>
                <h2 class="h5 fw-black mb-1" id="checkout-review-title">Terminos y revision</h2>
                <p class="small text-muted mb-0">Revisaremos nuevamente productos, entrega e importes sin crear el pedido.</p>
            </div>
        </div>

        @if($reviewErrors->has('review'))
            <div class="alert alert-warning mt-3 mb-0" role="alert" data-checkout-error>
                <i class="bi bi-exclamation-triangle-fill me-1" aria-hidden="true"></i>
                {{ $reviewErrors->first('review') }}
            </div>
        @endif

        @if(! $checkoutForm['has_saved_delivery'])
            <div class="alert alert-secondary mt-3 mb-0" role="status">
                Guarda primero tus datos de contacto y entrega para habilitar la revision.
            </div>
        @endif

        @if($checkoutForm['terms'])
            <input type="hidden" name="terms_document_id" value="{{ $checkoutForm['terms']['id'] }}">
            <div class="form-check mt-3">
                <input
                    class="form-check-input {{ $reviewErrors->has('terms_accepted') || $reviewErrors->has('terms_document_id') ? 'is-invalid' : '' }}"
                    id="checkout-terms-accepted"
                    name="terms_accepted"
                    type="checkbox"
                    value="1"
                    {{ $termsAccepted ? 'checked' : '' }}
                    required
                >
                <label class="form-check-label" for="checkout-terms-accepted">
                    Acepto los
                    <a href="{{ $checkoutForm['terms']['url'] }}" target="_blank" rel="noopener noreferrer">terminos y condiciones</a>.
                </label>
                @if($reviewErrors->has('terms_accepted') || $reviewErrors->has('terms_document_id'))
                    <div class="invalid-feedback d-block">
                        {{ $reviewErrors->first('terms_accepted') ?: $reviewErrors->first('terms_document_id') }}
                    </div>
                @endif
            </div>
        @else
            <div class="alert alert-danger mt-3 mb-0" role="alert">
                No existe una version publicada de los terminos. La revision esta temporalmente deshabilitada.
            </div>
        @endif

        @if($checkoutForm['privacy'])
            <p class="small text-muted mt-2 mb-0">
                Tus datos se trataran conforme a nuestra
                <a href="{{ $checkoutForm['privacy']['url'] }}" target="_blank" rel="noopener noreferrer">politica de privacidad</a>.
                Esta aceptacion no autoriza comunicaciones publicitarias.
            </p>
        @endif

        @if($checkoutForm['is_reviewed'])
            <div class="checkout-review-ready mt-3" role="status">
                <i class="bi bi-check-circle-fill" aria-hidden="true"></i>
                <div>
                    <strong>Revision completada</strong>
                    <span>Los datos e importes mostrados estan listos para el siguiente paso.</span>
                </div>
            </div>
        @endif

        @if($reviewErrors->has('quote_reference'))
            <div class="mt-3">
                <x-checkout.quote-change-notice
                    :message="$reviewErrors->first('quote_reference')"
                    :warnings="$warnings"
                />
            </div>
        @endif

        <div class="checkout-stage-actions mt-4">
            <a class="btn btn-vn-outline" href="{{ route('checkout.index', ['paso' => 1]) }}">
                <i class="bi bi-arrow-left me-1" aria-hidden="true"></i>
                Volver
            </a>
            <button class="btn btn-vn" type="submit" data-checkout-fiscal-submit @disabled(! $canReview)>
                <span>{{ $checkoutForm['is_reviewed'] ? 'Actualizar y continuar' : 'Continuar al pago' }}</span>
                <i class="bi bi-arrow-right ms-1" aria-hidden="true"></i>
            </button>
        </div>
    </section>
</form>
