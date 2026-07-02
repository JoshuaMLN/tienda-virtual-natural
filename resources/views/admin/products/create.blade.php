@extends('layouts.admin')

@section('title', 'Nuevo producto | VitaNatural Admin')
@section('adminActive', 'products')

@section('content')
<div class="d-flex justify-content-between align-items-end gap-3 mb-4">
    <div>
        <h1 class="h3 fw-black mb-1">Nuevo producto</h1>
        <p class="text-muted mb-0">Crea un producto real para el catalogo publico.</p>
    </div>
    <a class="btn btn-outline-secondary" href="{{ route('admin.products.index') }}"><i class="bi bi-arrow-left me-1"></i>Volver</a>
</div>

<div class="admin-card p-4">
    <form method="POST" action="{{ route('admin.products.store') }}" enctype="multipart/form-data">
        @csrf
        @include('admin.products.partials.form', [
            'product' => $product,
            'categories' => $categories,
            'brands' => $brands,
        ])
    </form>
</div>
@endsection
