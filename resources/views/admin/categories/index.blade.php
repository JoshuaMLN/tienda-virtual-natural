@extends('layouts.admin')

@section('title', 'Categorias | VitaNatural Admin')
@section('adminActive', 'categories')

@section('content')
@php
    $categories = [
        ['name' => 'Suplementos', 'slug' => 'suplementos', 'products' => 78, 'status' => 'Activo'],
        ['name' => 'Superfoods', 'slug' => 'superfoods', 'products' => 54, 'status' => 'Activo'],
        ['name' => 'Snacks saludables', 'slug' => 'snacks-saludables', 'products' => 46, 'status' => 'Activo'],
        ['name' => 'Proteinas', 'slug' => 'proteinas', 'products' => 32, 'status' => 'Activo'],
        ['name' => 'Vitaminas', 'slug' => 'vitaminas', 'products' => 28, 'status' => 'Activo'],
    ];
@endphp

<div class="d-flex justify-content-between align-items-end mb-4">
    <div><h1 class="h3 fw-black mb-1">Categorias</h1><p class="text-muted mb-0">Organiza las categorias de tu tienda.</p></div>
    <button class="btn btn-green" type="button"><i class="bi bi-plus-lg me-1"></i>Nueva categoria</button>
</div>

<div class="row g-4">
    <div class="col-xl-8">
        <div class="admin-card p-3">
            <table class="table mb-0">
                <thead><tr><th>Categoria</th><th>Slug</th><th>Productos</th><th>Estado</th><th></th></tr></thead>
                <tbody>
                    @foreach($categories as $category)
                        <tr>
                            <td><strong>{{ $category['name'] }}</strong></td>
                            <td>{{ $category['slug'] }}</td>
                            <td>{{ $category['products'] }}</td>
                            <td><x-admin.status-badge :status="$category['status']" /></td>
                            <td><button class="btn btn-sm btn-light" type="button" aria-label="Editar"><i class="bi bi-pencil"></i></button></td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    </div>
    <div class="col-xl-4">
        <div class="admin-card p-4">
            <h2 class="h5 fw-black">Nueva categoria</h2>
            <div class="d-grid gap-3">
                <input class="form-control" type="text" placeholder="Nombre de categoria">
                <input class="form-control" type="text" placeholder="Slug automatico">
                <textarea class="form-control" rows="4" placeholder="Descripcion"></textarea>
                <div class="border rounded-2 p-4 text-center">
                    <i class="bi bi-image fs-2 text-vn-green"></i>
                    <p class="small text-muted mb-0">Subir imagen</p>
                </div>
                <select class="form-select"><option>Activo</option><option>Inactivo</option></select>
                <button class="btn btn-vn" type="button">Guardar categoria</button>
            </div>
        </div>
    </div>
</div>
@endsection
