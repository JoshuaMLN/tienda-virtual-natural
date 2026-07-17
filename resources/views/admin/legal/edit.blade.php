@extends('layouts.admin')

@section('title', $legalDocument->type->label().' | VitaNatural Admin')
@section('adminActive', 'legal')

@section('content')
<div class="d-flex flex-wrap justify-content-between align-items-center gap-3 mb-4">
    <div>
        <a class="admin-back-link" href="{{ route('admin.legal.index') }}"><i class="bi bi-arrow-left"></i> Volver a configuracion legal</a>
        <h1 class="h3 fw-black mt-2 mb-1">{{ $legalDocument->type->label() }}</h1>
        <p class="text-muted mb-0">
            {{ $legalDocument->status->label() }}
            @if($legalDocument->version) &middot; Version {{ $legalDocument->version }} @endif
        </p>
    </div>
    <span class="badge {{ $legalDocument->status === \App\Enums\LegalDocumentStatus::Draft ? 'text-bg-warning' : ($legalDocument->status === \App\Enums\LegalDocumentStatus::Published ? 'text-bg-success' : 'text-bg-secondary') }} px-3 py-2">
        {{ $legalDocument->status->label() }}
    </span>
</div>

@if($draftIsStale)
    <div class="alert alert-warning d-flex flex-wrap justify-content-between align-items-center gap-3">
        <span>La configuracion legal cambio despues de crear este borrador.</span>
        <form method="POST" action="{{ route('admin.legal.documents.refresh-template', $legalDocument) }}">
            @csrf
            <button class="btn btn-sm btn-warning" type="submit"><i class="bi bi-arrow-repeat me-1"></i>Regenerar contenido</button>
        </form>
    </div>
@elseif($legalDocument->status === \App\Enums\LegalDocumentStatus::Draft)
    <div class="alert alert-info small">
        El contenido fue generado con la configuracion disponible al crear el borrador. Revisalo antes de publicar.
    </div>
@endif

<div class="admin-card p-3 p-lg-4">
    <form method="POST" action="{{ route('admin.legal.documents.update', $legalDocument) }}">
        @csrf
        @method('PUT')

        <div class="mb-3">
            <label class="form-label" for="title">Titulo <span class="required-mark">*</span></label>
            <input class="form-control @error('title') is-invalid @enderror" id="title" name="title" value="{{ old('title', $legalDocument->title) }}" @readonly($legalDocument->status !== \App\Enums\LegalDocumentStatus::Draft) required>
            @error('title')<div class="invalid-feedback">{{ $message }}</div>@enderror
        </div>

        <div class="mb-3">
            <label class="form-label" for="body">Contenido en Markdown <span class="required-mark">*</span></label>
            <textarea class="form-control legal-editor @error('body') is-invalid @enderror" id="body" name="body" rows="26" @readonly($legalDocument->status !== \App\Enums\LegalDocumentStatus::Draft) required>{{ old('body', $legalDocument->body) }}</textarea>
            @error('body')<div class="invalid-feedback">{{ $message }}</div>@enderror
        </div>

        @if($legalDocument->status === \App\Enums\LegalDocumentStatus::Draft)
            <div class="d-flex flex-wrap justify-content-between gap-2">
                <button class="btn btn-outline-danger" type="button" data-bs-toggle="modal" data-bs-target="#discardLegalDraftModal">
                    <i class="bi bi-trash me-1"></i>Descartar borrador
                </button>
                <div class="d-flex flex-wrap gap-2">
                    <button class="btn btn-vn-outline" type="submit"><i class="bi bi-save me-1"></i>Guardar borrador</button>
                    <button class="btn btn-vn" type="button" data-bs-toggle="modal" data-bs-target="#publishLegalDocumentModal">
                        <i class="bi bi-send-check me-1"></i>Publicar version
                    </button>
                </div>
            </div>
        @endif
    </form>
</div>

@if($legalDocument->status === \App\Enums\LegalDocumentStatus::Draft)
    <div class="modal fade" id="publishLegalDocumentModal" tabindex="-1" aria-labelledby="publishLegalDocumentModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-header">
                    <h2 class="modal-title fs-5" id="publishLegalDocumentModalLabel">Publicar nueva version</h2>
                    <button class="btn-close" type="button" data-bs-dismiss="modal" aria-label="Cerrar"></button>
                </div>
                <div class="modal-body">
                    La version activa actual sera reemplazada y permanecera disponible como historial inmutable.
                </div>
                <div class="modal-footer">
                    <button class="btn btn-outline-secondary" type="button" data-bs-dismiss="modal">Cancelar</button>
                    <form method="POST" action="{{ route('admin.legal.documents.publish', $legalDocument) }}">
                        @csrf
                        <button class="btn btn-vn" type="submit"><i class="bi bi-send-check me-1"></i>Confirmar publicacion</button>
                    </form>
                </div>
            </div>
        </div>
    </div>

    <div class="modal fade" id="discardLegalDraftModal" tabindex="-1" aria-labelledby="discardLegalDraftModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-header">
                    <h2 class="modal-title fs-5" id="discardLegalDraftModalLabel">Descartar borrador</h2>
                    <button class="btn-close" type="button" data-bs-dismiss="modal" aria-label="Cerrar"></button>
                </div>
                <div class="modal-body">Esta accion elimina el borrador. Las versiones publicadas no se veran afectadas.</div>
                <div class="modal-footer">
                    <button class="btn btn-outline-secondary" type="button" data-bs-dismiss="modal">Cancelar</button>
                    <form method="POST" action="{{ route('admin.legal.documents.destroy', $legalDocument) }}">
                        @csrf
                        @method('DELETE')
                        <button class="btn btn-danger" type="submit"><i class="bi bi-trash me-1"></i>Descartar</button>
                    </form>
                </div>
            </div>
        </div>
    </div>
@endif
@endsection
