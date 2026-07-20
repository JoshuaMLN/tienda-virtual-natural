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
                <div class="col-12">
                    <div class="form-label mb-2">Horarios de atencion</div>
                    <div class="row g-3">
                        <div class="col-lg-4">
                            <div class="small fw-bold mb-2">Lunes a viernes <span class="required-mark" aria-hidden="true">*</span></div>
                            <div class="row g-2">
                                <div class="col-6">
                                    <label class="form-label small" for="business_hours_weekdays_open">Apertura</label>
                                    <input class="form-control @error('business_hours_weekdays_open') is-invalid @enderror" id="business_hours_weekdays_open" name="business_hours_weekdays_open" type="time" value="{{ old('business_hours_weekdays_open', $storeSettings->weekdayOpenTime()) }}" required>
                                    @error('business_hours_weekdays_open')<div class="invalid-feedback">{{ $message }}</div>@enderror
                                </div>
                                <div class="col-6">
                                    <label class="form-label small" for="business_hours_weekdays_close">Cierre</label>
                                    <input class="form-control @error('business_hours_weekdays_close') is-invalid @enderror" id="business_hours_weekdays_close" name="business_hours_weekdays_close" type="time" value="{{ old('business_hours_weekdays_close', $storeSettings->weekdayCloseTime()) }}" required>
                                    @error('business_hours_weekdays_close')<div class="invalid-feedback">{{ $message }}</div>@enderror
                                </div>
                            </div>
                        </div>
                        <div class="col-lg-4">
                            <div class="small fw-bold mb-2">Sabado</div>
                            <div class="row g-2">
                                <div class="col-6">
                                    <label class="form-label small" for="business_hours_saturday_open">Apertura</label>
                                    <input class="form-control @error('business_hours_saturday_open') is-invalid @enderror" id="business_hours_saturday_open" name="business_hours_saturday_open" type="time" value="{{ old('business_hours_saturday_open', $storeSettings->saturdayOpenTime()) }}">
                                    @error('business_hours_saturday_open')<div class="invalid-feedback">{{ $message }}</div>@enderror
                                </div>
                                <div class="col-6">
                                    <label class="form-label small" for="business_hours_saturday_close">Cierre</label>
                                    <input class="form-control @error('business_hours_saturday_close') is-invalid @enderror" id="business_hours_saturday_close" name="business_hours_saturday_close" type="time" value="{{ old('business_hours_saturday_close', $storeSettings->saturdayCloseTime()) }}">
                                    @error('business_hours_saturday_close')<div class="invalid-feedback">{{ $message }}</div>@enderror
                                </div>
                            </div>
                            <div class="form-text">Deja ambas horas vacias si no se atiende.</div>
                        </div>
                        <div class="col-lg-4">
                            <div class="small fw-bold mb-2">Domingo</div>
                            <div class="row g-2">
                                <div class="col-6">
                                    <label class="form-label small" for="business_hours_sunday_open">Apertura</label>
                                    <input class="form-control @error('business_hours_sunday_open') is-invalid @enderror" id="business_hours_sunday_open" name="business_hours_sunday_open" type="time" value="{{ old('business_hours_sunday_open', $storeSettings->sundayOpenTime()) }}">
                                    @error('business_hours_sunday_open')<div class="invalid-feedback">{{ $message }}</div>@enderror
                                </div>
                                <div class="col-6">
                                    <label class="form-label small" for="business_hours_sunday_close">Cierre</label>
                                    <input class="form-control @error('business_hours_sunday_close') is-invalid @enderror" id="business_hours_sunday_close" name="business_hours_sunday_close" type="time" value="{{ old('business_hours_sunday_close', $storeSettings->sundayCloseTime()) }}">
                                    @error('business_hours_sunday_close')<div class="invalid-feedback">{{ $message }}</div>@enderror
                                </div>
                            </div>
                            <div class="form-text">Deja ambas horas vacias si no se atiende.</div>
                        </div>
                    </div>
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
                        <span class="input-group-text">dias de atencion</span>
                        @error('delivery_business_days_min')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    </div>
                    <div class="form-text">Se usa cuando un distrito no tiene un plazo propio.</div>
                </div>
                <div class="col-sm-6 col-xl-3">
                    <label class="form-label" for="delivery_business_days_max">Entrega maxima <span class="required-mark" aria-hidden="true">*</span></label>
                    <div class="input-group">
                        <input class="form-control @error('delivery_business_days_max') is-invalid @enderror" id="delivery_business_days_max" name="delivery_business_days_max" type="number" min="1" max="30" value="{{ old('delivery_business_days_max', $storeSettings->deliveryBusinessDaysMax()) }}" required>
                        <span class="input-group-text">dias de atencion</span>
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
                <div class="col-lg-6">
                    <label class="form-label" for="pickup_address">Direccion completa de recojo</label>
                    <textarea class="form-control @error('pickup_address') is-invalid @enderror" id="pickup_address" name="pickup_address" rows="2" placeholder="Avenida, numero, distrito y referencia">{{ old('pickup_address', $storeSettings->pickupAddress()) }}</textarea>
                    @error('pickup_address')<div class="invalid-feedback">{{ $message }}</div>@enderror
                </div>
                <div class="col-sm-6 col-lg-2">
                    <label class="form-label" for="pickup_preparation_business_days_min">Preparacion minima <span class="required-mark" aria-hidden="true">*</span></label>
                    <div class="input-group">
                        <input class="form-control @error('pickup_preparation_business_days_min') is-invalid @enderror" id="pickup_preparation_business_days_min" name="pickup_preparation_business_days_min" type="number" min="1" max="30" value="{{ old('pickup_preparation_business_days_min', $storeSettings->pickupPreparationBusinessDaysMin()) }}" required>
                        <span class="input-group-text">dias</span>
                        @error('pickup_preparation_business_days_min')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    </div>
                </div>
                <div class="col-sm-6 col-lg-2">
                    <label class="form-label" for="pickup_preparation_business_days_max">Preparacion maxima <span class="required-mark" aria-hidden="true">*</span></label>
                    <div class="input-group">
                        <input class="form-control @error('pickup_preparation_business_days_max') is-invalid @enderror" id="pickup_preparation_business_days_max" name="pickup_preparation_business_days_max" type="number" min="1" max="30" value="{{ old('pickup_preparation_business_days_max', $storeSettings->pickupPreparationBusinessDaysMax()) }}" required>
                        <span class="input-group-text">dias</span>
                        @error('pickup_preparation_business_days_max')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    </div>
                </div>
                <div class="col-lg-2">
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

<div class="admin-card p-3 p-lg-4 mb-4">
    <div class="d-flex flex-wrap justify-content-between align-items-start gap-3 mb-3">
        <div>
            <h2 class="h5 fw-black mb-1">Calendario de fechas sin atencion</h2>
            <p class="text-muted small mb-0">Estas fechas no cuentan al calcular entregas ni la preparacion de recojos.</p>
        </div>
    </div>

    <form class="row g-2 align-items-end mb-3" method="POST" action="{{ route('admin.settings.non-working-days.store') }}">
        @csrf
        <div class="col-sm-5 col-lg-3">
            <label class="form-label" for="non_working_date">Fecha <span class="required-mark" aria-hidden="true">*</span></label>
            <input class="form-control @if($errors->nonWorkingDay->has('date')) is-invalid @endif" id="non_working_date" name="date" type="date" min="{{ today()->toDateString() }}" value="{{ old('date') }}" required>
            @if($errors->nonWorkingDay->has('date'))<div class="invalid-feedback">{{ $errors->nonWorkingDay->first('date') }}</div>@endif
        </div>
        <div class="col-sm">
            <label class="form-label" for="non_working_reason">Motivo</label>
            <input class="form-control @if($errors->nonWorkingDay->has('reason')) is-invalid @endif" id="non_working_reason" name="reason" maxlength="120" value="{{ old('reason') }}" placeholder="Feriado, inventario o cierre extraordinario">
            @if($errors->nonWorkingDay->has('reason'))<div class="invalid-feedback">{{ $errors->nonWorkingDay->first('reason') }}</div>@endif
        </div>
        <div class="col-sm-auto">
            <button class="btn btn-vn w-100" type="submit"><i class="bi bi-calendar-plus me-1"></i>Agregar fecha</button>
        </div>
    </form>

    @if($nonWorkingDays->isEmpty())
        <div class="text-muted small py-2">No hay cierres futuros registrados.</div>
    @else
        <div class="list-group list-group-flush border-top">
            @foreach($nonWorkingDays as $nonWorkingDay)
                <div class="list-group-item px-0 d-flex justify-content-between align-items-center gap-3">
                    <div>
                        <strong>{{ $nonWorkingDay->date->format('d/m/Y') }}</strong>
                        <span class="text-muted ms-2">{{ $nonWorkingDay->reason ?: 'Sin motivo indicado' }}</span>
                    </div>
                    <form method="POST" action="{{ route('admin.settings.non-working-days.destroy', $nonWorkingDay) }}">
                        @csrf
                        @method('DELETE')
                        <button class="btn btn-sm btn-outline-danger" type="submit" aria-label="Quitar fecha del calendario" title="Quitar fecha"><i class="bi bi-trash"></i></button>
                    </form>
                </div>
            @endforeach
        </div>
    @endif
</div>

<div class="admin-card p-3">
    <div class="d-flex flex-wrap justify-content-between align-items-start gap-3 mb-3">
        <div>
            <h2 class="h5 fw-black mb-1">Tarifas por distrito</h2>
            <p class="text-muted small mb-0">Importes finales con IGV incluido, calculados inicialmente desde San Isidro. Una tarifa de S/ 0.00 significa entrega gratuita.</p>
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
                    <th>Tarifa final</th>
                    <th>Plazo estimado</th>
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
                        <td>
                            @if($district->delivery_business_days_min === null)
                                <span class="small">General: {{ $storeSettings->deliveryWindowLabel() }}</span>
                            @elseif($district->delivery_business_days_min === $district->delivery_business_days_max)
                                <span class="small">{{ $district->delivery_business_days_min }} dia(s) de atencion</span>
                            @else
                                <span class="small">{{ $district->delivery_business_days_min }} a {{ $district->delivery_business_days_max }} dias de atencion</span>
                            @endif
                        </td>
                        <td><x-admin.status-badge :status="$district->is_active ? 'Activo' : 'Inactivo'" /></td>
                        <td class="text-end">
                            <button class="btn btn-sm btn-light" type="button" data-bs-toggle="modal" data-bs-target="#deliveryDistrictModal-{{ $district->id }}" aria-label="Editar tarifa" title="Editar tarifa">
                                <i class="bi bi-pencil"></i>
                            </button>
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="7" class="text-center text-muted py-4">No se encontraron distritos.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>

    <div class="mt-3">{{ $districts->links() }}</div>
</div>

@foreach($districts as $district)
    @php($isCurrentDistrict = (int) old('_delivery_district_id') === $district->id)
    @php($usesDefaultWindow = $isCurrentDistrict ? (bool) old('use_default_delivery_window', true) : $district->delivery_business_days_min === null)
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
                            <label class="form-label" for="shipping_fee_{{ $district->id }}">Tarifa de envio (IGV incluido) <span class="required-mark" aria-hidden="true">*</span></label>
                            <div class="input-group">
                                <span class="input-group-text">S/</span>
                                <input class="form-control @if($isCurrentDistrict && $errors->deliveryDistrict->has('shipping_fee')) is-invalid @endif" id="shipping_fee_{{ $district->id }}" name="shipping_fee" type="number" min="0" max="999.99" step="0.01" value="{{ $isCurrentDistrict ? old('shipping_fee') : $district->shipping_fee }}" required>
                                @if($isCurrentDistrict && $errors->deliveryDistrict->has('shipping_fee'))
                                    <div class="invalid-feedback">{{ $errors->deliveryDistrict->first('shipping_fee') }}</div>
                                @endif
                            </div>
                            <div class="form-text">Ingresa el precio final que vera el cliente. Usa S/ 0.00 para ofrecer entrega gratuita en este distrito.</div>
                        </div>
                        <div class="mb-3">
                            <input type="hidden" name="use_default_delivery_window" value="0">
                            <div class="form-check form-switch mb-3">
                                <input class="form-check-input" id="use_default_delivery_window_{{ $district->id }}" name="use_default_delivery_window" type="checkbox" value="1" data-delivery-window-default @checked($usesDefaultWindow)>
                                <label class="form-check-label" for="use_default_delivery_window_{{ $district->id }}">Usar plazo general de {{ $storeSettings->deliveryWindowLabel() }}</label>
                            </div>
                            <div class="row g-3" data-delivery-window-fields>
                                <div class="col-6">
                                    <label class="form-label" for="delivery_business_days_min_{{ $district->id }}">Plazo minimo <span class="required-mark" aria-hidden="true">*</span></label>
                                    <div class="input-group">
                                        <input class="form-control @if($isCurrentDistrict && $errors->deliveryDistrict->has('delivery_business_days_min')) is-invalid @endif" id="delivery_business_days_min_{{ $district->id }}" name="delivery_business_days_min" type="number" min="1" max="30" value="{{ $isCurrentDistrict ? old('delivery_business_days_min', $storeSettings->deliveryBusinessDaysMin()) : ($district->delivery_business_days_min ?? $storeSettings->deliveryBusinessDaysMin()) }}">
                                        <span class="input-group-text">dias</span>
                                        @if($isCurrentDistrict && $errors->deliveryDistrict->has('delivery_business_days_min'))
                                            <div class="invalid-feedback">{{ $errors->deliveryDistrict->first('delivery_business_days_min') }}</div>
                                        @endif
                                    </div>
                                </div>
                                <div class="col-6">
                                    <label class="form-label" for="delivery_business_days_max_{{ $district->id }}">Plazo maximo <span class="required-mark" aria-hidden="true">*</span></label>
                                    <div class="input-group">
                                        <input class="form-control @if($isCurrentDistrict && $errors->deliveryDistrict->has('delivery_business_days_max')) is-invalid @endif" id="delivery_business_days_max_{{ $district->id }}" name="delivery_business_days_max" type="number" min="1" max="30" value="{{ $isCurrentDistrict ? old('delivery_business_days_max', $storeSettings->deliveryBusinessDaysMax()) : ($district->delivery_business_days_max ?? $storeSettings->deliveryBusinessDaysMax()) }}">
                                        <span class="input-group-text">dias</span>
                                        @if($isCurrentDistrict && $errors->deliveryDistrict->has('delivery_business_days_max'))
                                            <div class="invalid-feedback">{{ $errors->deliveryDistrict->first('delivery_business_days_max') }}</div>
                                        @endif
                                    </div>
                                </div>
                            </div>
                            <div class="form-text">El plazo se convierte en fechas usando los dias y cierres configurados.</div>
                        </div>
                        <input type="hidden" name="is_active" value="0">
                        <div class="form-check form-switch">
                            <input class="form-check-input" id="is_active_{{ $district->id }}" name="is_active" type="checkbox" value="1" @checked($isCurrentDistrict ? old('is_active') : $district->is_active)>
                            <label class="form-check-label" for="is_active_{{ $district->id }}">Distrito habilitado para entregas</label>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button class="btn btn-outline-secondary" type="button" data-bs-dismiss="modal">Cancelar</button>
                        <button class="btn btn-vn" type="submit"><i class="bi bi-save me-1"></i>Guardar distrito</button>
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

<script>
    document.addEventListener('DOMContentLoaded', function () {
        document.querySelectorAll('[data-delivery-window-default]').forEach(function (toggle) {
            var modal = toggle.closest('.modal');
            var fields = modal ? modal.querySelectorAll('[data-delivery-window-fields] input') : [];

            function syncWindowFields() {
                fields.forEach(function (field) {
                    field.disabled = toggle.checked;
                    field.required = !toggle.checked;
                });
            }

            toggle.addEventListener('change', syncWindowFields);
            syncWindowFields();
        });
    });
</script>
@endsection
