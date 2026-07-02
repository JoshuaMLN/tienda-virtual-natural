@extends('layouts.admin')

@section('title', 'Nueva categoria | VitaNatural Admin')
@section('adminActive', 'categories')

@section('content')
<div class="d-flex justify-content-between align-items-end gap-3 mb-4">
    <div>
        <h1 class="h3 fw-black mb-1">Nueva categoria</h1>
        <p class="text-muted mb-0">Crea una categoria para organizar el catalogo publico.</p>
    </div>
    <a class="btn btn-outline-secondary" href="{{ route('admin.categories.index') }}"><i class="bi bi-arrow-left me-1"></i>Volver</a>
</div>

<div class="admin-card p-4">
    <form method="POST" action="{{ route('admin.categories.store') }}" enctype="multipart/form-data">
        @csrf
        @include('admin.categories.partials.form', ['category' => $category, 'iconOptions' => $iconOptions])
    </form>
</div>
@endsection
