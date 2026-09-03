    @props(['document', 'order'])

@php
    $documentId = $document['id'];
    $correctionModalId = 'fiscal-correction-modal-'.$documentId;
    $annulmentModalId = 'fiscal-annulment-modal-'.$documentId;
    $relatedModalId = 'fiscal-related-modal-'.$documentId;
    $replacementModalId = 'fiscal-replacement-modal-'.$documentId;
    $correctionContext = 'fiscal-correction-'.$documentId;
@endphp

@if($document['can_correct'])
    <div class="d-flex flex-wrap gap-2 mt-3">
        <button class="btn btn-sm btn-outline-secondary" type="button" data-bs-toggle="modal" data-bs-target="#{{ $correctionModalId }}">
            <i class="bi bi-pencil-square me-1" aria-hidden="true"></i>Corregir comprobante
        </button>
        <button class="btn btn-sm btn-outline-danger" type="button" data-bs-toggle="modal" data-bs-target="#{{ $annulmentModalId }}">
            <i class="bi bi-x-octagon me-1" aria-hidden="true"></i>Registrar anulacion
        </button>
        <button class="btn btn-sm btn-outline-secondary" type="button" data-bs-toggle="modal" data-bs-target="#{{ $relatedModalId }}">
            <i class="bi bi-file-earmark-plus me-1" aria-hidden="true"></i>Registrar nota
        </button>
    </div>

    <div class="modal fade" id="{{ $correctionModalId }}" tabindex="-1" aria-labelledby="{{ $correctionModalId }}-title" aria-hidden="true" @if(old('form_context') === $correctionContext) data-modal-auto-open @endif>
        <div class="modal-dialog modal-dialog-centered modal-dialog-scrollable">
            <div class="modal-content">
                <form method="POST" action="{{ route('admin.orders.fiscal-documents.correct', [$order->code, $documentId]) }}" enctype="multipart/form-data">
                    @csrf
                    @method('PATCH')
                    <input name="form_context" type="hidden" value="{{ $correctionContext }}">
                    <div class="modal-header">
                        <div>
                            <h3 class="modal-title h5 fw-black mb-1" id="{{ $correctionModalId }}-title">Corregir comprobante</h3>
                            <p class="small text-muted mb-0">{{ $document['type'] }} {{ $document['reference'] }}</p>
                        </div>
                        <button class="btn-close" type="button" data-bs-dismiss="modal" aria-label="Cerrar"></button>
                    </div>
                    <div class="modal-body">
                        <p class="small text-muted">Actualiza solo los datos que difieren del comprobante oficial. Si adjuntas un PDF, reemplazara la version vigente; si no modificas un campo, conservara su valor actual.</p>

                        <div class="row g-2">
                            <div class="col-md-4">
                                <label class="form-label" for="fiscal-correction-series-{{ $documentId }}">Serie</label>
                                <input class="form-control @error('series') is-invalid @enderror" id="fiscal-correction-series-{{ $documentId }}" name="series" maxlength="10" value="{{ old('form_context') === $correctionContext ? old('series') : $document['series'] }}" required>
                                @error('series')<div class="invalid-feedback">{{ $message }}</div>@enderror
                            </div>
                            <div class="col-md-4">
                                <label class="form-label" for="fiscal-correction-correlative-{{ $documentId }}">Correlativo</label>
                                <input class="form-control @error('correlative') is-invalid @enderror" id="fiscal-correction-correlative-{{ $documentId }}" name="correlative" maxlength="20" value="{{ old('form_context') === $correctionContext ? old('correlative') : $document['correlative'] }}" required>
                                @error('correlative')<div class="invalid-feedback">{{ $message }}</div>@enderror
                            </div>
                            <div class="col-md-4">
                                <label class="form-label" for="fiscal-correction-issued-at-{{ $documentId }}">Fecha de emision</label>
                                <input class="form-control @error('issued_at') is-invalid @enderror" id="fiscal-correction-issued-at-{{ $documentId }}" name="issued_at" type="date" max="{{ now()->toDateString() }}" value="{{ old('form_context') === $correctionContext ? old('issued_at') : $document['issued_date'] }}" required>
                                @error('issued_at')<div class="invalid-feedback">{{ $message }}</div>@enderror
                            </div>
                        </div>

                        <div class="mt-3">
                            <label class="form-label" for="fiscal-correction-pdf-{{ $documentId }}">Reemplazar PDF oficial <span class="text-muted fw-normal">(opcional)</span></label>
                            <input class="form-control @error('pdf') is-invalid @enderror" id="fiscal-correction-pdf-{{ $documentId }}" name="pdf" type="file" accept="application/pdf,.pdf">
                            <div class="form-text">PDF, maximo 10 MB. El archivo anterior permanecera versionado de forma privada.</div>
                            @error('pdf')<div class="invalid-feedback">{{ $message }}</div>@enderror
                        </div>

                        <div class="mt-3">
                            <label class="form-label" for="fiscal-correction-reason-{{ $documentId }}">Motivo de la correccion <span class="text-danger" aria-hidden="true">*</span></label>
                            <textarea class="form-control @error('reason') is-invalid @enderror" id="fiscal-correction-reason-{{ $documentId }}" name="reason" rows="3" maxlength="500" required>{{ old('form_context') === $correctionContext ? old('reason') : '' }}</textarea>
                            @error('reason')<div class="invalid-feedback">{{ $message }}</div>@enderror
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button class="btn btn-outline-secondary" type="button" data-bs-dismiss="modal">Cancelar</button>
                        <button class="btn btn-primary" type="submit"><i class="bi bi-check2-circle me-1" aria-hidden="true"></i>Guardar correccion</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <div class="modal fade" id="{{ $annulmentModalId }}" tabindex="-1" aria-labelledby="{{ $annulmentModalId }}-title" aria-hidden="true" @if(old('form_context') === 'fiscal-annulment-'.$documentId) data-modal-auto-open @endif>
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content border-danger">
                <form method="POST" action="{{ route('admin.orders.fiscal-documents.annul', [$order->code, $documentId]) }}">
                    @csrf
                    @method('PATCH')
                    <input name="form_context" type="hidden" value="fiscal-annulment-{{ $documentId }}">
                    <div class="modal-header">
                        <div>
                            <h3 class="modal-title h5 fw-black text-danger mb-1" id="{{ $annulmentModalId }}-title">Registrar anulacion</h3>
                            <p class="small text-muted mb-0">{{ $document['type'] }} {{ $document['reference'] }}</p>
                        </div>
                        <button class="btn-close" type="button" data-bs-dismiss="modal" aria-label="Cerrar"></button>
                    </div>
                    <div class="modal-body">
                        <div class="alert alert-warning small" role="note">
                            Registra esta accion solo despues de realizar la anulacion o el tratamiento fiscal correspondiente fuera de VitaNatural.
                        </div>
                        <label class="form-label" for="fiscal-annulment-reason-{{ $documentId }}">Motivo y referencia externa</label>
                        <textarea class="form-control @error('reason') is-invalid @enderror" id="fiscal-annulment-reason-{{ $documentId }}" name="reason" rows="3" maxlength="500" required>{{ old('form_context') === 'fiscal-annulment-'.$documentId ? old('reason') : '' }}</textarea>
                        @error('reason')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    </div>
                    <div class="modal-footer">
                        <button class="btn btn-outline-secondary" type="button" data-bs-dismiss="modal">Cancelar</button>
                        <button class="btn btn-danger" type="submit"><i class="bi bi-x-octagon me-1" aria-hidden="true"></i>Registrar anulacion</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <div class="modal fade" id="{{ $relatedModalId }}" tabindex="-1" aria-labelledby="{{ $relatedModalId }}-title" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered modal-dialog-scrollable">
            <div class="modal-content">
                <form method="POST" action="{{ route('admin.orders.fiscal-documents.related.store', [$order->code, $documentId]) }}" enctype="multipart/form-data">
                    @csrf
                    <div class="modal-header"><h3 class="modal-title h5 fw-black" id="{{ $relatedModalId }}-title">Registrar nota relacionada</h3><button class="btn-close" type="button" data-bs-dismiss="modal" aria-label="Cerrar"></button></div>
                    <div class="modal-body">
                        <p class="small text-muted">Registra una nota oficial ya emitida externamente para {{ $document['reference'] }}.</p>
                        <label class="form-label" for="fiscal-related-type-{{ $documentId }}">Tipo</label>
                        <select class="form-select" id="fiscal-related-type-{{ $documentId }}" name="type"><option value="credit_note">Nota de credito</option><option value="debit_note">Nota de debito</option></select>
                        <div class="row g-2 mt-1"><div class="col-md-4"><label class="form-label" for="fiscal-related-series-{{ $documentId }}">Serie</label><input class="form-control" id="fiscal-related-series-{{ $documentId }}" name="series" required></div><div class="col-md-4"><label class="form-label" for="fiscal-related-correlative-{{ $documentId }}">Correlativo</label><input class="form-control" id="fiscal-related-correlative-{{ $documentId }}" name="correlative" required></div><div class="col-md-4"><label class="form-label" for="fiscal-related-issued-at-{{ $documentId }}">Fecha de emision</label><input class="form-control" id="fiscal-related-issued-at-{{ $documentId }}" name="issued_at" type="date" max="{{ now()->toDateString() }}" required></div></div>
                        <label class="form-label mt-3" for="fiscal-related-pdf-{{ $documentId }}">PDF oficial</label><input class="form-control" id="fiscal-related-pdf-{{ $documentId }}" name="pdf" type="file" accept="application/pdf,.pdf" required>
                    </div>
                    <div class="modal-footer"><button class="btn btn-outline-secondary" type="button" data-bs-dismiss="modal">Cancelar</button><button class="btn btn-primary" type="submit">Registrar nota</button></div>
                </form>
            </div>
        </div>
    </div>
@endif

@if($document['can_register_replacement'])
    <button class="btn btn-sm btn-outline-primary mt-3" type="button" data-bs-toggle="modal" data-bs-target="#{{ $replacementModalId }}">
        <i class="bi bi-arrow-repeat me-1" aria-hidden="true"></i>Registrar reemplazo
    </button>
    <div class="modal fade" id="{{ $replacementModalId }}" tabindex="-1" aria-labelledby="{{ $replacementModalId }}-title" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered modal-dialog-scrollable"><div class="modal-content"><form method="POST" action="{{ route('admin.orders.fiscal-documents.replacement.store', [$order->code, $documentId]) }}" enctype="multipart/form-data">@csrf
            <div class="modal-header"><h3 class="modal-title h5 fw-black" id="{{ $replacementModalId }}-title">Registrar reemplazo emitido externamente</h3><button class="btn-close" type="button" data-bs-dismiss="modal" aria-label="Cerrar"></button></div>
            <div class="modal-body"><p class="small text-muted">Registra el nuevo comprobante oficial que reemplaza a {{ $document['reference'] }} tras su anulacion externa.</p><div class="row g-2"><div class="col-md-4"><label class="form-label" for="fiscal-replacement-series-{{ $documentId }}">Serie</label><input class="form-control" id="fiscal-replacement-series-{{ $documentId }}" name="series" required></div><div class="col-md-4"><label class="form-label" for="fiscal-replacement-correlative-{{ $documentId }}">Correlativo</label><input class="form-control" id="fiscal-replacement-correlative-{{ $documentId }}" name="correlative" required></div><div class="col-md-4"><label class="form-label" for="fiscal-replacement-issued-at-{{ $documentId }}">Fecha de emision</label><input class="form-control" id="fiscal-replacement-issued-at-{{ $documentId }}" name="issued_at" type="date" max="{{ now()->toDateString() }}" required></div></div><label class="form-label mt-3" for="fiscal-replacement-pdf-{{ $documentId }}">PDF oficial</label><input class="form-control" id="fiscal-replacement-pdf-{{ $documentId }}" name="pdf" type="file" accept="application/pdf,.pdf" required></div>
            <div class="modal-footer"><button class="btn btn-outline-secondary" type="button" data-bs-dismiss="modal">Cancelar</button><button class="btn btn-primary" type="submit">Registrar reemplazo</button></div>
        </form></div></div>
    </div>
@endif
