@extends('layouts.admin')

@section('title', 'Legal | VitaNatural Admin')
@section('adminActive', 'legal')

@section('content')
<div class="d-flex flex-wrap justify-content-between align-items-end gap-3 mb-4">
    <div>
        <h1 class="h3 fw-black mb-1">Configuracion legal</h1>
        <p class="text-muted mb-0">Gestiona la identidad del proveedor, las reglas comerciales y sus documentos publicados.</p>
    </div>
    <span class="badge rounded-pill {{ $readiness->isDemoMode() ? 'text-bg-warning' : 'text-bg-success' }} px-3 py-2">
        <i class="bi {{ $readiness->isDemoMode() ? 'bi-cone-striped' : 'bi-check-circle' }} me-1"></i>
        {{ $readiness->isDemoMode() ? 'Modo demostrativo' : 'Ventas reales habilitadas' }}
    </span>
</div>

@if($missingRequirements !== [])
    <div class="alert alert-warning" role="alert">
        <div class="fw-bold mb-1"><i class="bi bi-info-circle me-1"></i>Requisitos pendientes para ventas reales</div>
        <div class="small">{{ implode(' · ', $missingRequirements) }}</div>
    </div>
@endif

<div class="admin-card p-3 p-lg-4 mb-4">
    <form method="POST" action="{{ route('admin.legal.settings.update') }}" class="d-flex flex-column gap-3">
        @csrf
        @method('PATCH')

        <section class="admin-form-section">
            <div class="admin-form-section-header">
                <h2 class="h6 fw-black mb-1">Identidad del proveedor</h2>
                <p class="text-muted mb-0">Estos valores se incorporan como snapshot al publicar una nueva version legal.</p>
            </div>
            <div class="row g-3">
                <div class="col-md-6">
                    <label class="form-label" for="legal_trade_name">Nombre comercial <span class="required-mark" aria-hidden="true">*</span></label>
                    <input class="form-control @error('legal_trade_name') is-invalid @enderror" id="legal_trade_name" name="legal_trade_name" value="{{ old('legal_trade_name', $storeSettings->legalTradeName()) }}" required>
                    @error('legal_trade_name')<div class="invalid-feedback">{{ $message }}</div>@enderror
                </div>
                <div class="col-md-6">
                    <label class="form-label" for="legal_provider_name">Razon social o nombre del titular</label>
                    <input class="form-control @error('legal_provider_name') is-invalid @enderror" id="legal_provider_name" name="legal_provider_name" value="{{ old('legal_provider_name', $storeSettings->legalProviderName()) }}">
                    @error('legal_provider_name')<div class="invalid-feedback">{{ $message }}</div>@enderror
                </div>
                <div class="col-md-4">
                    <label class="form-label" for="legal_tax_id">RUC</label>
                    <input class="form-control @error('legal_tax_id') is-invalid @enderror" id="legal_tax_id" name="legal_tax_id" inputmode="numeric" maxlength="11" value="{{ old('legal_tax_id', $storeSettings->legalTaxId()) }}">
                    @error('legal_tax_id')<div class="invalid-feedback">{{ $message }}</div>@enderror
                </div>
                <div class="col-md-8">
                    <label class="form-label" for="legal_fiscal_address">Domicilio fiscal</label>
                    <input class="form-control @error('legal_fiscal_address') is-invalid @enderror" id="legal_fiscal_address" name="legal_fiscal_address" value="{{ old('legal_fiscal_address', $storeSettings->legalFiscalAddress()) }}">
                    @error('legal_fiscal_address')<div class="invalid-feedback">{{ $message }}</div>@enderror
                </div>
                <div class="col-12">
                    <label class="form-label" for="legal_complaints_book_url">Enlace al Libro de Reclamaciones</label>
                    <input class="form-control @error('legal_complaints_book_url') is-invalid @enderror" id="legal_complaints_book_url" name="legal_complaints_book_url" type="url" placeholder="https://" value="{{ old('legal_complaints_book_url', $storeSettings->legalComplaintsBookUrl()) }}">
                    @error('legal_complaints_book_url')<div class="invalid-feedback">{{ $message }}</div>@enderror
                </div>
            </div>
            <div class="alert alert-light border mt-3 mb-0 small">
                <i class="bi bi-telephone me-1 text-vn-green"></i>
                Los canales y horarios proceden de
                <a class="fw-bold" href="{{ route('admin.settings.edit') }}">Configuracion operativa</a>:
                {{ $storeSettings->email() }}, WhatsApp {{ $storeSettings->whatsappDisplay() }}.
            </div>
        </section>

        <section class="admin-form-section admin-form-section-emphasis">
            <div class="admin-form-section-header">
                <h2 class="h6 fw-black mb-1">Politicas comerciales</h2>
                <p class="text-muted mb-0">Los pedidos conservaran las reglas vigentes al momento de su confirmacion.</p>
            </div>
            <div class="row g-3">
                <div class="col-sm-6 col-xl-4">
                    <label class="form-label" for="incident_report_hours">Aviso preferente de incidentes <span class="required-mark">*</span></label>
                    <div class="input-group">
                        <input class="form-control @error('incident_report_hours') is-invalid @enderror" id="incident_report_hours" name="incident_report_hours" type="number" min="1" max="720" value="{{ old('incident_report_hours', $storeSettings->incidentReportHours()) }}" required>
                        <span class="input-group-text">horas</span>
                        @error('incident_report_hours')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    </div>
                </div>
                <div class="col-sm-6 col-xl-4">
                    <label class="form-label" for="refund_processing_business_days">Procesamiento del reembolso <span class="required-mark">*</span></label>
                    <div class="input-group">
                        <input class="form-control @error('refund_processing_business_days') is-invalid @enderror" id="refund_processing_business_days" name="refund_processing_business_days" type="number" min="1" max="30" value="{{ old('refund_processing_business_days', $storeSettings->refundProcessingBusinessDays()) }}" required>
                        <span class="input-group-text">dias habiles</span>
                        @error('refund_processing_business_days')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    </div>
                </div>
                <div class="col-sm-6 col-xl-4">
                    <label class="form-label" for="pickup_hold_days">Plazo para recoger <span class="required-mark">*</span></label>
                    <div class="input-group">
                        <input class="form-control @error('pickup_hold_days') is-invalid @enderror" id="pickup_hold_days" name="pickup_hold_days" type="number" min="1" max="60" value="{{ old('pickup_hold_days', $storeSettings->pickupHoldDays()) }}" required>
                        <span class="input-group-text">dias calendario</span>
                        @error('pickup_hold_days')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    </div>
                </div>
                <div class="col-sm-6 col-xl-4">
                    <label class="form-label" for="delivery_attempts_per_cycle">Intentos por tarifa <span class="required-mark">*</span></label>
                    <div class="input-group">
                        <input class="form-control @error('delivery_attempts_per_cycle') is-invalid @enderror" id="delivery_attempts_per_cycle" name="delivery_attempts_per_cycle" type="number" min="1" max="10" value="{{ old('delivery_attempts_per_cycle', $storeSettings->deliveryAttemptsPerCycle()) }}" required>
                        <span class="input-group-text">intentos</span>
                        @error('delivery_attempts_per_cycle')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    </div>
                </div>
                <div class="col-sm-6 col-xl-4">
                    <label class="form-label" for="delivery_max_automatic_cycles">Ciclos automaticos <span class="required-mark">*</span></label>
                    <div class="input-group">
                        <input class="form-control @error('delivery_max_automatic_cycles') is-invalid @enderror" id="delivery_max_automatic_cycles" name="delivery_max_automatic_cycles" type="number" min="1" max="5" value="{{ old('delivery_max_automatic_cycles', $storeSettings->deliveryMaxAutomaticCycles()) }}" required>
                        <span class="input-group-text">ciclos</span>
                        @error('delivery_max_automatic_cycles')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    </div>
                </div>
                <div class="col-sm-6 col-xl-4">
                    <label class="form-label" for="reshipment_payment_days">Pago del nuevo envio <span class="required-mark">*</span></label>
                    <div class="input-group">
                        <input class="form-control @error('reshipment_payment_days') is-invalid @enderror" id="reshipment_payment_days" name="reshipment_payment_days" type="number" min="1" max="30" value="{{ old('reshipment_payment_days', $storeSettings->reshipmentPaymentDays()) }}" required>
                        <span class="input-group-text">dias calendario</span>
                        @error('reshipment_payment_days')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    </div>
                </div>
            </div>
        </section>

        <section class="admin-form-section">
            <div class="admin-form-section-header">
                <h2 class="h6 fw-black mb-1">Modo comercial</h2>
                <p class="text-muted mb-0">Las ventas reales solo se habilitan con identidad completa y documentos republicados.</p>
            </div>
            <input type="hidden" name="live_sales_enabled" value="0">
            <div class="form-check form-switch">
                <input class="form-check-input" id="live_sales_enabled" name="live_sales_enabled" type="checkbox" value="1" @checked(old('live_sales_enabled', $storeSettings->liveSalesRequested()))>
                <label class="form-check-label fw-bold" for="live_sales_enabled">Permitir ventas reales</label>
            </div>
        </section>

        <div>
            <button class="btn btn-vn" type="submit"><i class="bi bi-save me-1"></i>Guardar configuracion legal</button>
        </div>
    </form>
</div>

<div class="admin-card p-3 p-lg-4">
    <div class="d-flex flex-wrap justify-content-between align-items-start gap-3 mb-3">
        <div>
            <h2 class="h5 fw-black mb-1">Documentos legales</h2>
            <p class="text-muted small mb-0">Publicar una version reemplaza la anterior sin modificar su contenido historico.</p>
        </div>
    </div>

    <div class="table-responsive">
        <table class="table align-middle mb-0">
            <thead>
                <tr>
                    <th>Documento</th>
                    <th>Version activa</th>
                    <th>Borrador</th>
                    <th>Publicacion</th>
                    <th class="text-end">Acciones</th>
                </tr>
            </thead>
            <tbody>
                @foreach($documentTypes as $type)
                    @php
                        $typeDocuments = $documents->get($type->value, collect());
                        $activeDocument = $typeDocuments->first(fn ($document) => $document->status === \App\Enums\LegalDocumentStatus::Published);
                        $draftDocument = $typeDocuments->first(fn ($document) => $document->status === \App\Enums\LegalDocumentStatus::Draft);
                    @endphp
                    <tr>
                        <td><strong>{{ $type->label() }}</strong></td>
                        <td>{{ $activeDocument ? 'v'.$activeDocument->version : 'Sin publicar' }}</td>
                        <td>
                            @if($draftDocument)
                                <span class="badge text-bg-warning">Borrador</span>
                            @else
                                <span class="text-muted">Ninguno</span>
                            @endif
                        </td>
                        <td>{{ $activeDocument?->published_at?->format('d/m/Y H:i') ?? '-' }}</td>
                        <td class="text-end">
                            <div class="d-inline-flex flex-wrap justify-content-end gap-2">
                                @if($activeDocument)
                                    <a class="btn btn-sm btn-light" href="{{ route($type->routeName()) }}" target="_blank" rel="noopener" aria-label="Ver {{ $type->label() }}">
                                        <i class="bi bi-box-arrow-up-right"></i>
                                    </a>
                                @endif
                                @if($draftDocument)
                                    <a class="btn btn-sm btn-vn-outline" href="{{ route('admin.legal.documents.edit', $draftDocument) }}">
                                        <i class="bi bi-pencil me-1"></i>Editar
                                    </a>
                                @else
                                    <form method="POST" action="{{ route('admin.legal.documents.store') }}">
                                        @csrf
                                        <input type="hidden" name="type" value="{{ $type->value }}">
                                        <button class="btn btn-sm btn-vn-outline" type="submit">
                                            <i class="bi bi-file-earmark-plus me-1"></i>Nueva version
                                        </button>
                                    </form>
                                @endif
                            </div>
                        </td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    </div>

    @php
        $documentHistory = $documents->flatten(1)
            ->filter(fn ($document) => $document->version !== null)
            ->sortByDesc(fn ($document) => [$document->published_at?->timestamp ?? 0, $document->id]);
    @endphp
    <div class="border-top mt-4 pt-4">
        <h3 class="h6 fw-black mb-3">Historial de versiones</h3>
        <div class="table-responsive">
            <table class="table table-sm align-middle mb-0">
                <thead>
                    <tr>
                        <th>Documento</th>
                        <th>Version</th>
                        <th>Estado</th>
                        <th>Publicada</th>
                        <th class="text-end">Accion</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($documentHistory as $document)
                        <tr>
                            <td>{{ $document->type->label() }}</td>
                            <td>v{{ $document->version }}</td>
                            <td>
                                <span class="badge {{ $document->status === \App\Enums\LegalDocumentStatus::Published ? 'text-bg-success' : 'text-bg-secondary' }}">
                                    {{ $document->status->label() }}
                                </span>
                            </td>
                            <td>{{ $document->published_at?->format('d/m/Y H:i') ?? '-' }}</td>
                            <td class="text-end">
                                <a class="btn btn-sm btn-light" href="{{ route('admin.legal.documents.edit', $document) }}" aria-label="Ver version {{ $document->version }}">
                                    <i class="bi bi-eye"></i>
                                </a>
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    </div>
</div>
@endsection
