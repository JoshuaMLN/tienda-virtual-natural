@extends('layouts.admin')

@section('title', 'Editar categoria | VitaNatural Admin')
@section('adminActive', 'categories')

@section('content')
<div class="d-flex justify-content-between align-items-end gap-3 mb-4">
    <div>
        <h1 class="h3 fw-black mb-1">Editar categoria</h1>
        <p class="text-muted mb-0">{{ $category->name }}</p>
    </div>
    <a class="btn btn-outline-secondary" href="{{ route('admin.categories.index') }}"><i class="bi bi-arrow-left me-1"></i>Volver</a>
</div>

<div class="admin-card p-4">
    <form method="POST" action="{{ route('admin.categories.update', $category) }}" enctype="multipart/form-data">
        @csrf
        @method('PUT')
        @include('admin.categories.partials.form', ['category' => $category, 'iconOptions' => $iconOptions])
    </form>
</div>
@endsection
