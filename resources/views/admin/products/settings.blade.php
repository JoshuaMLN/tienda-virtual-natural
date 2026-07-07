@extends('layouts.admin')

@section('title', 'Configuracion de productos | VitaNatural Admin')
@section('adminActive', 'products')

@section('content')
<div class="d-flex justify-content-between align-items-end gap-3 mb-4">
    <div>
        <h1 class="h3 fw-black mb-1">Configuracion de productos</h1>
        <p class="text-muted mb-0">Define como se muestra la disponibilidad en la tienda publica.</p>
    </div>
    <a class="btn btn-outline-secondary" href="{{ route('admin.products.index') }}"><i class="bi bi-arrow-left me-1"></i>Volver</a>
</div>

<div class="admin-card p-4">
    <form method="POST" action="{{ route('admin.products.settings.update') }}" class="row g-3">
        @csrf
        @method('PATCH')

        <div class="col-lg-5">
            <label class="form-label" for="public_stock_display_threshold">
                Umbral publico de disponibilidad <span class="required-mark" aria-hidden="true">*</span><span class="visually-hidden"> obligatorio</span>
            </label>
            <input
                class="form-control @error('public_stock_display_threshold') is-invalid @enderror"
                id="public_stock_display_threshold"
                name="public_stock_display_threshold"
                type="number"
                min="0"
                value="{{ old('public_stock_display_threshold', $publicStockDisplayThreshold) }}"
                required
            >
            @error('public_stock_display_threshold')<div class="invalid-feedback">{{ $message }}</div>@enderror
            <div class="form-text">Con 0 se mostrara solo "En stock". Con 10, la tienda marcara pocas unidades cuando el stock sea 10 o menor.</div>
        </div>

        <div class="col-12">
            <button class="btn btn-vn" type="submit"><i class="bi bi-save me-1"></i>Guardar configuracion</button>
        </div>
    </form>
</div>
@endsection
