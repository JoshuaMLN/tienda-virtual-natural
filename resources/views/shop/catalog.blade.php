@extends('layouts.shop')

@section('title', 'Catalogo | VitaNatural')

@section('content')
@php
    $products = [
        ['name' => 'Vitamina D3 2000 UI', 'description' => '90 capsulas', 'price' => 'S/ 49.90', 'rating' => 5, 'reviews' => 42, 'image' => 'https://images.unsplash.com/photo-1587854692152-cbe660dbde88?auto=format&fit=crop&w=700&q=80'],
        ['name' => 'Magnesio citrato', 'description' => '120 capsulas', 'price' => 'S/ 49.90', 'old_price' => 'S/ 59.90', 'rating' => 5, 'reviews' => 47, 'image' => 'https://images.unsplash.com/photo-1471864190281-a93a3070b6de?auto=format&fit=crop&w=700&q=80'],
        ['name' => 'Zinc 25 mg', 'description' => '100 tabletas', 'price' => 'S/ 26.90', 'rating' => 4, 'reviews' => 31, 'image' => 'https://images.unsplash.com/photo-1628771065518-0d82f1938462?auto=format&fit=crop&w=700&q=80'],
        ['name' => 'Omega 3 Premium', 'description' => '120 capsulas', 'price' => 'S/ 79.90', 'rating' => 5, 'reviews' => 73, 'image' => 'https://images.unsplash.com/photo-1587854692152-cbe660dbde88?auto=format&fit=crop&w=700&q=80'],
        ['name' => 'Colageno hidrolizado', 'description' => 'Sobre 200 g', 'price' => 'S/ 69.90', 'rating' => 4, 'reviews' => 55, 'image' => 'https://images.unsplash.com/photo-1605296867304-46d5465a13f1?auto=format&fit=crop&w=700&q=80'],
        ['name' => 'Ashwagandha', 'description' => '60 capsulas', 'price' => 'S/ 51.90', 'old_price' => 'S/ 64.90', 'rating' => 5, 'reviews' => 24, 'image' => 'https://images.unsplash.com/photo-1516684669134-de6f7c473a2a?auto=format&fit=crop&w=700&q=80'],
        ['name' => 'Vitamina C 1000 mg', 'description' => '60 tabletas', 'price' => 'S/ 59.00', 'rating' => 5, 'reviews' => 120, 'image' => 'https://images.unsplash.com/photo-1611080626919-7cf5a9dbab5b?auto=format&fit=crop&w=700&q=80'],
        ['name' => 'Complejo B', 'description' => '100 tabletas', 'price' => 'S/ 42.90', 'rating' => 4, 'reviews' => 48, 'image' => 'https://images.unsplash.com/photo-1550572017-edd951aa8f72?auto=format&fit=crop&w=700&q=80'],
        ['name' => 'Maca negra en polvo', 'description' => '200 g', 'price' => 'S/ 34.90', 'rating' => 5, 'reviews' => 96, 'image' => 'https://images.unsplash.com/photo-1587049352851-8d4e89133924?auto=format&fit=crop&w=700&q=80'],
    ];
@endphp

<section class="container py-4">
    <nav class="small text-muted mb-3">Inicio &gt; Catalogo</nav>
    <div class="d-flex flex-wrap align-items-end justify-content-between gap-3 mb-4">
        <div>
            <h1 class="section-title mb-1">Suplementos</h1>
            <p class="text-muted mb-0">Mostrando 1-12 de 126 productos</p>
        </div>
        <div class="d-flex gap-2">
            <button class="btn btn-vn-outline d-lg-none" data-bs-toggle="offcanvas" data-bs-target="#mobileFilters" type="button">
                <i class="bi bi-sliders"></i> Filtros
            </button>
            <select class="form-select" aria-label="Ordenar productos">
                <option>Mas vendidos</option>
                <option>Menor precio</option>
                <option>Mayor precio</option>
                <option>Novedades</option>
            </select>
        </div>
    </div>

    <div class="row g-4">
        <aside class="col-lg-3 d-none d-lg-block">
            @include('shop.partials.filters')
        </aside>
        <div class="col-lg-9">
            <div class="row g-3 g-xl-4">
                @foreach($products as $product)
                    <div class="col-6 col-md-4">
                        <x-shop.product-card :product="$product" />
                    </div>
                @endforeach
            </div>
            <nav class="mt-4" aria-label="Paginacion">
                <ul class="pagination justify-content-center">
                    <li class="page-item active"><a class="page-link" href="#">1</a></li>
                    <li class="page-item"><a class="page-link" href="#">2</a></li>
                    <li class="page-item"><a class="page-link" href="#">3</a></li>
                    <li class="page-item"><a class="page-link" href="#">4</a></li>
                    <li class="page-item"><a class="page-link" href="#">5</a></li>
                    <li class="page-item"><a class="page-link" href="#"><i class="bi bi-chevron-right"></i></a></li>
                </ul>
            </nav>
        </div>
    </div>
</section>

<div class="offcanvas offcanvas-start" tabindex="-1" id="mobileFilters" aria-labelledby="mobileFiltersLabel">
    <div class="offcanvas-header">
        <h5 id="mobileFiltersLabel">Filtrar por</h5>
        <button class="btn-close" data-bs-dismiss="offcanvas" type="button" aria-label="Cerrar"></button>
    </div>
    <div class="offcanvas-body">
        @include('shop.partials.filters')
    </div>
</div>
@endsection
