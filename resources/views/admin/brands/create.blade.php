@extends('layouts.admin')

@section('title', 'Nueva marca | VitaNatural Admin')
@section('adminActive', 'brands')

@section('content')
<div class="d-flex justify-content-between align-items-end gap-3 mb-4">
    <div>
        <h1 class="h3 fw-black mb-1">Nueva marca</h1>
        <p class="text-muted mb-0">Crea una marca para asociarla a productos y filtros del catalogo.</p>
    </div>
    <a class="btn btn-outline-secondary" href="{{ route('admin.brands.index') }}"><i class="bi bi-arrow-left me-1"></i>Volver</a>
</div>

<div class="admin-card p-4">
    <form method="POST" action="{{ route('admin.brands.store') }}" enctype="multipart/form-data">
        @csrf
        @include('admin.brands.partials.form', ['brand' => $brand])
    </form>
</div>
@endsection
