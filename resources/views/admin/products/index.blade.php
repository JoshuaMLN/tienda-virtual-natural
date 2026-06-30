@extends('layouts.admin')

@section('title', 'Productos | VitaNatural Admin')
@section('adminActive', 'products')

@section('content')
@php
    $products = [
        ['name' => 'Omega 3 Premium', 'category' => 'Suplementos', 'price' => 'S/ 79.90', 'stock' => 45, 'status' => 'Activo', 'image' => 'https://images.unsplash.com/photo-1587854692152-cbe660dbde88?auto=format&fit=crop&w=300&q=80'],
        ['name' => 'Maca negra en polvo', 'category' => 'Superfoods', 'price' => 'S/ 34.90', 'stock' => 8, 'status' => 'Activo', 'image' => 'https://images.unsplash.com/photo-1587049352851-8d4e89133924?auto=format&fit=crop&w=300&q=80'],
        ['name' => 'Mix de frutos secos', 'category' => 'Snacks saludables', 'price' => 'S/ 26.90', 'stock' => 32, 'status' => 'Activo', 'image' => 'https://images.unsplash.com/photo-1599599810769-bcde5a160d32?auto=format&fit=crop&w=300&q=80'],
        ['name' => 'Proteina vegana vainilla', 'category' => 'Proteinas', 'price' => 'S/ 89.90', 'stock' => 15, 'status' => 'Activo', 'image' => 'https://images.unsplash.com/photo-1605296867304-46d5465a13f1?auto=format&fit=crop&w=300&q=80'],
        ['name' => 'Vitamina C 1000 mg', 'category' => 'Suplementos', 'price' => 'S/ 59.00', 'stock' => 60, 'status' => 'Activo', 'image' => 'https://images.unsplash.com/photo-1611080626919-7cf5a9dbab5b?auto=format&fit=crop&w=300&q=80'],
    ];
@endphp

<div class="d-flex justify-content-between align-items-end gap-3 mb-4">
    <div><h1 class="h3 fw-black mb-1">Productos</h1><p class="text-muted mb-0">Administra el catalogo de productos.</p></div>
    <div class="d-flex gap-2">
        <button class="btn btn-green" type="button"><i class="bi bi-plus-lg me-1"></i>Nuevo producto</button>
        <button class="btn btn-outline-secondary" type="button"><i class="bi bi-download me-1"></i>Exportar</button>
    </div>
</div>

<div class="admin-card p-3">
    <div class="row g-2 mb-3">
        <div class="col-md"><input class="form-control" type="search" placeholder="Buscar producto..."></div>
        <div class="col-md"><select class="form-select"><option>Todas las categorias</option></select></div>
        <div class="col-md"><select class="form-select"><option>Estado: Todos</option></select></div>
    </div>
    <div class="table-responsive">
        <table class="table mb-0">
            <thead><tr><th>Imagen</th><th>Producto</th><th>Categoria</th><th>Precio</th><th>Stock</th><th>Estado</th><th>Acciones</th></tr></thead>
            <tbody>
                @foreach($products as $product)
                    <tr>
                        <td><div class="thumb-sm" style="background-image: url('{{ $product['image'] }}')"></div></td>
                        <td><strong>{{ $product['name'] }}</strong></td>
                        <td>{{ $product['category'] }}</td>
                        <td>{{ $product['price'] }}</td>
                        <td>{{ $product['stock'] }}</td>
                        <td><x-admin.status-badge :status="$product['status']" /></td>
                        <td class="text-nowrap">
                            <button class="btn btn-sm btn-light" type="button" aria-label="Ver"><i class="bi bi-eye"></i></button>
                            <button class="btn btn-sm btn-light" type="button" aria-label="Editar"><i class="bi bi-pencil"></i></button>
                            <button class="btn btn-sm btn-light" type="button" aria-label="Eliminar"><i class="bi bi-trash"></i></button>
                        </td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    </div>
</div>
@endsection
