@extends('layouts.admin')

@section('title', 'Configuracion | VitaNatural Admin')
@section('adminActive', 'settings')

@section('content')
<div class="d-flex flex-wrap justify-content-between align-items-end gap-3 mb-4">
    <div>
        <h1 class="h3 fw-black mb-1">Configuracion operativa</h1>
        <p class="text-muted mb-0">Centraliza la atencion, entrega, recojo y tarifas de la tienda.</p>
    </div>
</div>

<div class="admin-card p-3 p-lg-4 mb-4">
    <form method="POST" action="{{ route('admin.settings.update') }}" class="d-flex flex-column gap-3">
        @csrf
        @method('PATCH')

        <section class="admin-form-section">
            <div class="admin-form-section-header">
                <h3 class="h6 fw-black mb-1">Atencion al cliente</h3>
                <p class="text-muted mb-0">Estos datos se muestran en el navbar, footer y pagina de contacto.</p>
            </div>
            <div class="row g-3">
                <div class="col-md-4">
                    <label class="form-label" for="contact_whatsapp">WhatsApp <span class="required-mark" aria-hidden="true">*</span></label>
                    <input class="form-control @error('contact_whatsapp') is-invalid @enderror" id="contact_whatsapp" name="contact_whatsapp" inputmode="numeric" value="{{ old('contact_whatsapp', $storeSettings->whatsapp()) }}" required>
                    @error('contact_whatsapp')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    <div class="form-text">Celular peruano de 9 digitos.</div>
                </div>
                <div class="col-md-4">
                    <label class="form-label" for="contact_email">Correo de contacto <span class="required-mark" aria-hidden="true">*</span></label>
                    <input class="form-control @error('contact_email') is-invalid @enderror" id="contact_email" name="contact_email" type="email" value="{{ old('contact_email', $storeSettings->email()) }}" required>
                    @error('contact_email')<div class="invalid-feedback">{{ $message }}</div>@enderror
                </div>
                <div class="col-md-4">
                    <label class="form-label" for="contact_phone">Telefono</label>
                    <input class="form-control @error('contact_phone') is-invalid @enderror" id="contact_phone" name="contact_phone" value="{{ old('contact_phone', $storeSettings->phone()) }}">
                    @error('contact_phone')<div class="invalid-feedback">{{ $message }}</div>@enderror
                </div>
                <div class="col-md-6">
                    <label class="form-label" for="business_hours_weekdays">Horario de lunes a viernes <span class="required-mark" aria-hidden="true">*</span></label>
                    <input class="form-control @error('business_hours_weekdays') is-invalid @enderror" id="business_hours_weekdays" name="business_hours_weekdays" value="{{ old('business_hours_weekdays', $storeSettings->weekdayHours()) }}" required>
                    @error('business_hours_weekdays')<div class="invalid-feedback">{{ $message }}</div>@enderror
                </div>
                <div class="col-md-6">
                    <label class="form-label" for="business_hours_saturday">Horario de sabado</label>
                    <input class="form-control @error('business_hours_saturday') is-invalid @enderror" id="business_hours_saturday" name="business_hours_saturday" value="{{ old('business_hours_saturday', $storeSettings->saturdayHours()) }}">
                    @error('business_hours_saturday')<div class="invalid-feedback">{{ $message }}</div>@enderror
                </div>
            </div>
        </section>

        <section class="admin-form-section admin-form-section-emphasis">
            <div class="admin-form-section-header">
                <h3 class="h6 fw-black mb-1">Reglas de entrega</h3>
                <p class="text-muted mb-0">Valores globales que se aplicaran al checkout y las reservas.</p>
            </div>
            <div class="row g-3">
                <div class="col-sm-6 col-xl-3">
                    <label class="form-label" for="free_shipping_threshold">Envio gratis desde <span class="required-mark" aria-hidden="true">*</span></label>
                    <div class="input-group">
                        <span class="input-group-text">S/</span>
                        <input class="form-control @error('free_shipping_threshold') is-invalid @enderror" id="free_shipping_threshold" name="free_shipping_threshold" type="number" min="0" max="99999.99" step="0.01" value="{{ old('free_shipping_threshold', $storeSettings->freeShippingThreshold()) }}" required>
                        @error('free_shipping_threshold')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    </div>
                    <div class="form-text">Usa 0 para deshabilitarlo.</div>
                </div>
                <div class="col-sm-6 col-xl-3">
                    <label class="form-label" for="stock_reservation_minutes">Tiempo para completar el pago <span class="required-mark" aria-hidden="true">*</span></label>
                    <div class="input-group">
                        <input class="form-control @error('stock_reservation_minutes') is-invalid @enderror" id="stock_reservation_minutes" name="stock_reservation_minutes" type="number" min="5" max="1440" value="{{ old('stock_reservation_minutes', $storeSettings->stockReservationMinutes()) }}" required>
                        <span class="input-group-text">minutos</span>
                        @error('stock_reservation_minutes')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    </div>
                    <div class="form-text">El stock queda reservado durante este tiempo mientras el cliente completa el pago.</div>
                </div>
                <div class="col-sm-6 col-xl-3">
                    <label class="form-label" for="delivery_business_days_min">Entrega minima <span class="required-mark" aria-hidden="true">*</span></label>
                    <div class="input-group">
                        <input class="form-control @error('delivery_business_days_min') is-invalid @enderror" id="delivery_business_days_min" name="delivery_business_days_min" type="number" min="1" max="30" value="{{ old('delivery_business_days_min', $storeSettings->deliveryBusinessDaysMin()) }}" required>
                        <span class="input-group-text">dias habiles</span>
                        @error('delivery_business_days_min')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    </div>
                </div>
                <div class="col-sm-6 col-xl-3">
                    <label class="form-label" for="delivery_business_days_max">Entrega maxima <span class="required-mark" aria-hidden="true">*</span></label>
                    <div class="input-group">
                        <input class="form-control @error('delivery_business_days_max') is-invalid @enderror" id="delivery_business_days_max" name="delivery_business_days_max" type="number" min="1" max="30" value="{{ old('delivery_business_days_max', $storeSettings->deliveryBusinessDaysMax()) }}" required>
                        <span class="input-group-text">dias habiles</span>
                        @error('delivery_business_days_max')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    </div>
                </div>
            </div>
        </section>

        <section class="admin-form-section">
            <div class="admin-form-section-header">
                <h3 class="h6 fw-black mb-1">Recojo en tienda</h3>
                <p class="text-muted mb-0">La opcion permanecera oculta mientras no exista una direccion completa.</p>
            </div>
            <div class="row g-3 align-items-end">
                <div class="col-lg-9">
                    <label class="form-label" for="pickup_address">Direccion completa de recojo</label>
                    <textarea class="form-control @error('pickup_address') is-invalid @enderror" id="pickup_address" name="pickup_address" rows="2" placeholder="Avenida, numero, distrito y referencia">{{ old('pickup_address', $storeSettings->pickupAddress()) }}</textarea>
                    @error('pickup_address')<div class="invalid-feedback">{{ $message }}</div>@enderror
                </div>
                <div class="col-lg-3">
                    @if($storeSettings->pickupEnabled())
                        <div class="alert alert-success py-2 mb-0"><i class="bi bi-check-circle me-1"></i>Recojo habilitado</div>
                    @else
                        <div class="alert alert-warning py-2 mb-0"><i class="bi bi-exclamation-circle me-1"></i>Recojo deshabilitado</div>
                    @endif
                </div>
            </div>
        </section>

        <div>
            <button class="btn btn-vn" type="submit"><i class="bi bi-save me-1"></i>Guardar configuracion</button>
        </div>
    </form>
</div>

<div class="admin-card p-3">
    <div class="d-flex flex-wrap justify-content-between align-items-start gap-3 mb-3">
        <div>
            <h2 class="h5 fw-black mb-1">Tarifas por distrito</h2>
            <p class="text-muted small mb-0">Precios iniciales calculados desde San Isidro. Todos son editables.</p>
        </div>
        <div class="admin-summary-chips d-flex flex-wrap">
            <span class="admin-summary-chip"><span>Total</span><strong>{{ $districtSummary['total'] }}</strong></span>
            <span class="admin-summary-chip admin-summary-chip-success"><span>Activos</span><strong>{{ $districtSummary['active'] }}</strong></span>
            <span class="admin-summary-chip admin-summary-chip-muted"><span>Inactivos</span><strong>{{ $districtSummary['inactive'] }}</strong></span>
        </div>
    </div>

    <form class="row g-2 mb-3" method="GET" action="{{ route('admin.settings.edit') }}">
        <div class="col-md">
            <div class="input-group">
                <span class="input-group-text bg-white"><i class="bi bi-search"></i></span>
                <input class="form-control" name="q" type="search" value="{{ request('q') }}" placeholder="Buscar distrito o UBIGEO...">
            </div>
        </div>
        <div class="col-md-auto">
            <select class="form-select" name="province">
                <option value="">Todas las provincias</option>
                @foreach($provinces as $province)
                    <option value="{{ $province->province_code }}" @selected(request('province') === $province->province_code)>{{ $province->province }}</option>
                @endforeach
            </select>
        </div>
        <div class="col-md-auto">
            <select class="form-select" name="status">
                <option value="">Todos los estados</option>
                <option value="active" @selected(request('status') === 'active')>Activos</option>
                <option value="inactive" @selected(request('status') === 'inactive')>Inactivos</option>
            </select>
        </div>
        <div class="col-md-auto d-flex gap-2">
            <button class="btn btn-vn" type="submit"><i class="bi bi-funnel me-1"></i>Filtrar</button>
            <a class="btn btn-outline-secondary" href="{{ route('admin.settings.edit') }}" aria-label="Limpiar filtros"><i class="bi bi-x-lg"></i></a>
        </div>
    </form>

    <div class="table-responsive">
        <table class="table align-middle mb-0">
            <thead>
                <tr>
                    <th>Distrito</th>
                    <th>Provincia</th>
                    <th>UBIGEO</th>
                    <th>Tarifa</th>
                    <th>Estado</th>
                    <th class="text-end">Acciones</th>
                </tr>
            </thead>
            <tbody>
                @forelse($districts as $district)
                    <tr>
                        <td><strong>{{ $district->district }}</strong></td>
                        <td>{{ $district->province }}</td>
                        <td><code>{{ $district->ubigeo }}</code></td>
                        <td>S/ {{ number_format((float) $district->shipping_fee, 2) }}</td>
                        <td><x-admin.status-badge :status="$district->is_active ? 'Activo' : 'Inactivo'" /></td>
                        <td class="text-end">
                            <button class="btn btn-sm btn-light" type="button" data-bs-toggle="modal" data-bs-target="#deliveryDistrictModal-{{ $district->id }}" aria-label="Editar tarifa" title="Editar tarifa">
                                <i class="bi bi-pencil"></i>
                            </button>
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="6" class="text-center text-muted py-4">No se encontraron distritos.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>

    <div class="mt-3">{{ $districts->links() }}</div>
</div>

@foreach($districts as $district)
    @php($isCurrentDistrict = (int) old('_delivery_district_id') === $district->id)
    <div class="modal fade" id="deliveryDistrictModal-{{ $district->id }}" tabindex="-1" aria-labelledby="deliveryDistrictModalLabel-{{ $district->id }}" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content">
                <form method="POST" action="{{ route('admin.settings.districts.update', $district) }}">
                    @csrf
                    @method('PATCH')
                    <input type="hidden" name="_delivery_district_id" value="{{ $district->id }}">
                    <div class="modal-header">
                        <div>
                            <h2 class="modal-title fs-5" id="deliveryDistrictModalLabel-{{ $district->id }}">{{ $district->district }}</h2>
                            <div class="small text-muted">{{ $district->province }} &middot; UBIGEO {{ $district->ubigeo }}</div>
                        </div>
                        <button class="btn-close" type="button" data-bs-dismiss="modal" aria-label="Cerrar"></button>
                    </div>
                    <div class="modal-body">
                        <div class="mb-3">
                            <label class="form-label" for="shipping_fee_{{ $district->id }}">Tarifa de envio <span class="required-mark" aria-hidden="true">*</span></label>
                            <div class="input-group">
                                <span class="input-group-text">S/</span>
                                <input class="form-control @if($isCurrentDistrict && $errors->deliveryDistrict->has('shipping_fee')) is-invalid @endif" id="shipping_fee_{{ $district->id }}" name="shipping_fee" type="number" min="0" max="999.99" step="0.01" value="{{ $isCurrentDistrict ? old('shipping_fee') : $district->shipping_fee }}" required>
                                @if($isCurrentDistrict && $errors->deliveryDistrict->has('shipping_fee'))
                                    <div class="invalid-feedback">{{ $errors->deliveryDistrict->first('shipping_fee') }}</div>
                                @endif
                            </div>
                        </div>
                        <input type="hidden" name="is_active" value="0">
                        <div class="form-check form-switch">
                            <input class="form-check-input" id="is_active_{{ $district->id }}" name="is_active" type="checkbox" value="1" @checked($isCurrentDistrict ? old('is_active') : $district->is_active)>
                            <label class="form-check-label" for="is_active_{{ $district->id }}">Distrito habilitado para entregas</label>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button class="btn btn-outline-secondary" type="button" data-bs-dismiss="modal">Cancelar</button>
                        <button class="btn btn-vn" type="submit"><i class="bi bi-save me-1"></i>Guardar tarifa</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
@endforeach

@if($errors->deliveryDistrict->any() && old('_delivery_district_id'))
    <script>
        document.addEventListener('DOMContentLoaded', function () {
            var modal = document.getElementById('deliveryDistrictModal-{{ (int) old('_delivery_district_id') }}');

            if (modal && window.bootstrap) {
                window.bootstrap.Modal.getOrCreateInstance(modal).show();
            }
        });
    </script>
@endif
@endsection
