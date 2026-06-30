@extends('layouts.admin')

@section('title', 'Banners y promociones | VitaNatural Admin')
@section('adminActive', 'banners')

@section('content')
@php
    $banners = [
        ['title' => 'Vive bien, vive natural', 'text' => 'Productos organicos y saludables para tu vida plena.', 'category' => 'Toda la tienda', 'status' => 'Activo', 'order' => 1, 'image' => 'https://images.unsplash.com/photo-1542838132-92c53300491e?auto=format&fit=crop&w=300&q=80'],
        ['title' => 'Omega 3 Premium', 'text' => 'Salud cardiovascular y cerebral.', 'category' => 'Suplementos', 'status' => 'Activo', 'order' => 2, 'image' => 'https://images.unsplash.com/photo-1587854692152-cbe660dbde88?auto=format&fit=crop&w=300&q=80'],
        ['title' => 'Hasta 15%', 'text' => 'En tu primera compra con cupon.', 'category' => 'Toda la tienda', 'status' => 'Activo', 'order' => 3, 'image' => 'https://images.unsplash.com/photo-1615485290382-441e4d049cb5?auto=format&fit=crop&w=300&q=80'],
        ['title' => 'Snacks saludables', 'text' => 'Opciones nutritivas para cada dia.', 'category' => 'Snacks saludables', 'status' => 'Activo', 'order' => 4, 'image' => 'https://images.unsplash.com/photo-1599599810769-bcde5a160d32?auto=format&fit=crop&w=300&q=80'],
    ];
@endphp

<div class="d-flex justify-content-between align-items-end mb-4">
    <div><h1 class="h3 fw-black mb-1">Banners y promociones</h1><p class="text-muted mb-0">Gestiona los banners y promociones de la tienda.</p></div>
    <button class="btn btn-green" type="button"><i class="bi bi-plus-lg me-1"></i>Nuevo banner</button>
</div>

<div class="admin-card p-3 mb-4">
    <ul class="nav nav-tabs mb-3">
        <li class="nav-item"><button class="nav-link active" type="button">Banners</button></li>
        <li class="nav-item"><button class="nav-link" type="button">Promociones</button></li>
        <li class="nav-item"><button class="nav-link" type="button">Popups</button></li>
    </ul>
    <div class="table-responsive">
        <table class="table mb-0">
            <thead><tr><th>Vista</th><th>Titulo</th><th>Texto</th><th>Categoria</th><th>Estado</th><th>Orden</th><th>Acciones</th></tr></thead>
            <tbody>
                @foreach($banners as $banner)
                    <tr>
                        <td><div class="thumb-sm" style="background-image: url('{{ $banner['image'] }}')"></div></td>
                        <td><strong>{{ $banner['title'] }}</strong></td>
                        <td>{{ $banner['text'] }}</td>
                        <td>{{ $banner['category'] }}</td>
                        <td><x-admin.status-badge :status="$banner['status']" /></td>
                        <td>{{ $banner['order'] }}</td>
                        <td class="text-nowrap">
                            <button class="btn btn-sm btn-light" type="button" aria-label="Editar"><i class="bi bi-pencil"></i></button>
                            <button class="btn btn-sm btn-light" type="button" aria-label="Eliminar"><i class="bi bi-trash"></i></button>
                        </td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    </div>
</div>

<div class="admin-card overflow-hidden">
    <div class="p-3 border-bottom"><h2 class="h5 fw-black mb-0">Vista previa del banner principal</h2></div>
    <div class="hero-panel d-flex align-items-center" style="min-height: 280px;">
        <div class="p-4">
            <h3 class="section-title">Vive bien,<br>vive natural</h3>
            <p class="text-muted">Productos organicos y saludables para tu vida plena.</p>
            <button class="btn btn-vn" type="button">Ver productos</button>
        </div>
    </div>
</div>
@endsection
