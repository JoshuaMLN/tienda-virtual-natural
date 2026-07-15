@extends('layouts.account')

@php
    $editing = $address->exists;
    $addressErrors = $errors->getBag('address');
    $selectedProvince = old('province_code', $address->ubigeo ? substr($address->ubigeo, 0, 4) : '');
    $selectedDistrict = old('district_code', $address->ubigeo ?? '');
    $selectedLocation = $selectedProvince !== '' ? ($locationCatalog[$selectedProvince] ?? null) : null;
    $department = $selectedLocation['department'] ?? '';
    $isLockedDefault = $isFirstAddress || (bool) $address->is_default;
    $isDefault = $isLockedDefault || (bool) old('is_default', false);
@endphp

@section('title', ($editing ? 'Editar direccion' : 'Nueva direccion').' | VitaNatural')
@section('accountActive', 'addresses')

@section('accountContent')
<div class="mb-4">
    <a class="account-back-link" href="{{ route('account.addresses') }}">
        <i class="bi bi-arrow-left" aria-hidden="true"></i> Volver a mis direcciones
    </a>
    <h1 class="section-title mb-1 mt-3">{{ $editing ? 'Editar direccion' : 'Agregar direccion' }}</h1>
    <p class="text-muted mb-0">Los campos con <span class="required-mark">*</span> son obligatorios.</p>
</div>

<form
    class="account-card address-form p-3 p-md-4"
    method="POST"
    action="{{ $editing ? route('account.addresses.update', $address) : route('account.addresses.store') }}"
    data-address-form
    data-selected-province="{{ $selectedProvince }}"
    data-selected-district="{{ $selectedDistrict }}"
>
    @csrf
    @if($editing)
        @method('PUT')
    @endif

    <script type="application/json" data-address-location-catalog>@json($locationCatalog)</script>

    <div class="address-area-note mb-4" role="note">
        <i class="bi bi-truck" aria-hidden="true"></i>
        <strong>Entrega disponible solo en Lima Metropolitana y Callao.</strong>
    </div>

    <section class="address-form-section" aria-labelledby="address-identification-title">
        <div class="address-form-section-title">
            <i class="bi bi-tag" aria-hidden="true"></i>
            <div>
                <h2 class="h5 fw-black mb-1" id="address-identification-title">Identificacion y contacto</h2>
                <p class="text-muted small mb-0">Usa una etiqueta sencilla como Casa, Trabajo o Familia.</p>
            </div>
        </div>
        <div class="row g-3">
            <div class="col-md-6">
                <label class="form-label" for="address-label">Etiqueta <span class="required-mark">*</span></label>
                <input
                    class="form-control {{ $addressErrors->has('label') ? 'is-invalid' : '' }}"
                    id="address-label"
                    name="label"
                    type="text"
                    maxlength="50"
                    value="{{ old('label', $address->label) }}"
                    placeholder="Ej. Casa"
                    required
                    autofocus
                >
                @if($addressErrors->has('label'))
                    <div class="invalid-feedback">{{ $addressErrors->first('label') }}</div>
                @endif
            </div>
            <div class="col-md-6">
                <label class="form-label" for="address-recipient">Persona que recibe <span class="required-mark">*</span></label>
                <input
                    class="form-control {{ $addressErrors->has('recipient_name') ? 'is-invalid' : '' }}"
                    id="address-recipient"
                    name="recipient_name"
                    type="text"
                    maxlength="120"
                    value="{{ old('recipient_name', $address->recipient_name) }}"
                    required
                >
                @if($addressErrors->has('recipient_name'))
                    <div class="invalid-feedback">{{ $addressErrors->first('recipient_name') }}</div>
                @endif
            </div>
            <div class="col-md-6">
                <label class="form-label" for="address-phone">Celular de contacto <span class="required-mark">*</span></label>
                <input
                    class="form-control {{ $addressErrors->has('phone') ? 'is-invalid' : '' }}"
                    id="address-phone"
                    name="phone"
                    type="tel"
                    inputmode="numeric"
                    maxlength="9"
                    pattern="9[0-9]{8}"
                    value="{{ old('phone', $address->phone) }}"
                    placeholder="987654321"
                    required
                >
                @if($addressErrors->has('phone'))
                    <div class="invalid-feedback">{{ $addressErrors->first('phone') }}</div>
                @endif
            </div>
        </div>
    </section>

    <section class="address-form-section" aria-labelledby="address-location-title">
        <div class="address-form-section-title">
            <i class="bi bi-geo-alt" aria-hidden="true"></i>
            <div>
                <h2 class="h5 fw-black mb-1" id="address-location-title">Ubicacion de entrega</h2>
                <p class="text-muted small mb-0">El departamento y el UBIGEO se completan automaticamente.</p>
            </div>
        </div>
        <div class="row g-3">
            <div class="col-md-6">
                <label class="form-label" for="address-area">Area de cobertura</label>
                <input class="form-control" id="address-area" type="text" value="Lima Metropolitana y Callao" readonly>
            </div>
            <div class="col-md-6">
                <label class="form-label" for="address-department">Departamento</label>
                <input class="form-control" id="address-department" type="text" value="{{ $department }}" placeholder="Se completara automaticamente" readonly data-address-department>
            </div>
            <div class="col-md-6">
                <label class="form-label" for="address-province">Provincia <span class="required-mark">*</span></label>
                <select class="form-select {{ $addressErrors->has('province_code') ? 'is-invalid' : '' }}" id="address-province" name="province_code" required data-address-province>
                    <option value="">Selecciona una provincia</option>
                    @foreach($locationCatalog as $provinceCode => $province)
                        <option value="{{ $provinceCode }}" {{ (string) $selectedProvince === (string) $provinceCode ? 'selected' : '' }}>{{ $province['name'] }}</option>
                    @endforeach
                </select>
                @if($addressErrors->has('province_code'))
                    <div class="invalid-feedback">{{ $addressErrors->first('province_code') }}</div>
                @endif
            </div>
            <div class="col-md-6">
                <label class="form-label" for="address-district">Distrito <span class="required-mark">*</span></label>
                <select class="form-select {{ $addressErrors->has('district_code') ? 'is-invalid' : '' }}" id="address-district" name="district_code" required data-address-district>
                    <option value="">Selecciona primero una provincia</option>
                    @if($selectedLocation)
                        @foreach($selectedLocation['districts'] as $district)
                            <option value="{{ $district['code'] }}" {{ (string) $selectedDistrict === (string) $district['code'] ? 'selected' : '' }}>{{ $district['name'] }}</option>
                        @endforeach
                    @endif
                </select>
                @if($addressErrors->has('district_code'))
                    <div class="invalid-feedback">{{ $addressErrors->first('district_code') }}</div>
                @endif
            </div>
            <div class="col-md-6">
                <label class="form-label" for="address-ubigeo">UBIGEO</label>
                <input class="form-control" id="address-ubigeo" type="text" value="{{ $selectedDistrict }}" placeholder="Se completara automaticamente" readonly data-address-ubigeo>
            </div>
        </div>
    </section>

    <section class="address-form-section" aria-labelledby="address-detail-title">
        <div class="address-form-section-title">
            <i class="bi bi-house-door" aria-hidden="true"></i>
            <div>
                <h2 class="h5 fw-black mb-1" id="address-detail-title">Detalle de la direccion</h2>
                <p class="text-muted small mb-0">Incluye avenida, calle, numero, interior o lote cuando corresponda.</p>
            </div>
        </div>
        <div class="row g-3">
            <div class="col-12">
                <label class="form-label" for="address-line">Direccion <span class="required-mark">*</span></label>
                <input
                    class="form-control {{ $addressErrors->has('address_line') ? 'is-invalid' : '' }}"
                    id="address-line"
                    name="address_line"
                    type="text"
                    maxlength="255"
                    value="{{ old('address_line', $address->address_line) }}"
                    placeholder="Ej. Av. Caminos del Inca 1234, dpto. 502"
                    required
                >
                @if($addressErrors->has('address_line'))
                    <div class="invalid-feedback">{{ $addressErrors->first('address_line') }}</div>
                @endif
            </div>
            <div class="col-12">
                <label class="form-label" for="address-reference">Referencia</label>
                <textarea
                    class="form-control {{ $addressErrors->has('reference') ? 'is-invalid' : '' }}"
                    id="address-reference"
                    name="reference"
                    rows="3"
                    maxlength="255"
                    placeholder="Ej. Frente al parque, puerta verde"
                >{{ old('reference', $address->reference) }}</textarea>
                @if($addressErrors->has('reference'))
                    <div class="invalid-feedback">{{ $addressErrors->first('reference') }}</div>
                @endif
            </div>
        </div>
    </section>

    <div class="address-default-setting">
        <input type="hidden" name="is_default" value="{{ $isLockedDefault ? '1' : '0' }}">
        <div class="form-check">
            <input
                class="form-check-input"
                id="address-is-default"
                name="is_default"
                type="checkbox"
                value="1"
                {{ $isDefault ? 'checked' : '' }}
                {{ $isLockedDefault ? 'disabled' : '' }}
            >
            <label class="form-check-label fw-bold" for="address-is-default">Usar como direccion predeterminada</label>
        </div>
        <p class="small text-muted mb-0 mt-1">
            @if($isFirstAddress)
                Tu primera direccion sera predeterminada automaticamente.
            @elseif($address->is_default)
                No puedes dejar tu cuenta sin una direccion predeterminada. Puedes elegir otra desde el listado.
            @else
                La usaremos primero al preparar una compra.
            @endif
        </p>
    </div>

    <div class="address-form-actions">
        <a class="btn btn-vn-outline" href="{{ route('account.addresses') }}">Cancelar</a>
        <button class="btn btn-vn" type="submit">
            <i class="bi bi-check-lg" aria-hidden="true"></i> {{ $editing ? 'Guardar cambios' : 'Guardar direccion' }}
        </button>
    </div>
</form>
@endsection
