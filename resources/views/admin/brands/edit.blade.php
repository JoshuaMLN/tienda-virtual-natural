@extends('layouts.admin')

@section('title', 'Editar marca | VitaNatural Admin')
@section('adminActive', 'brands')

@section('content')
<div class="d-flex justify-content-between align-items-end gap-3 mb-4">
    <div>
        <h1 class="h3 fw-black mb-1">Editar marca</h1>
        <p class="text-muted mb-0">{{ $brand->name }}</p>
    </div>
    <a class="btn btn-outline-secondary" href="{{ route('admin.brands.index') }}"><i class="bi bi-arrow-left me-1"></i>Volver</a>
</div>

<div class="admin-card p-4">
    <form method="POST" action="{{ route('admin.brands.update', $brand) }}" enctype="multipart/form-data">
        @csrf
        @method('PUT')
        @include('admin.brands.partials.form', ['brand' => $brand])
    </form>
</div>
@endsection
