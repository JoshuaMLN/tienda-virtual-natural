@extends('layouts.shop')

@section('title', 'Catalogo | VitaNatural')

@section('content')
@php
    $catalogTitle = $selectedCategories->count() === 1 ? $selectedCategories->first()->name : 'Catalogo';
@endphp

<section class="container py-4">
    <nav class="small text-muted mb-3">Inicio &gt; Catalogo</nav>
    <div class="d-flex flex-wrap align-items-end justify-content-between gap-3 mb-4">
        <div>
            <h1 class="section-title mb-1">{{ $catalogTitle }}</h1>
            <p class="text-muted mb-0">
                Mostrando {{ $products->firstItem() ?? 0 }}-{{ $products->lastItem() ?? 0 }} de {{ $products->total() }} productos
            </p>
        </div>
        <div class="d-flex gap-2">
            <button class="btn btn-vn-outline d-lg-none" data-bs-toggle="offcanvas" data-bs-target="#mobileFilters" type="button">
                <i class="bi bi-sliders"></i> Filtros
            </button>
            <form action="{{ route('shop.catalog') }}" method="GET">
                @foreach(request()->except(['orden', 'page']) as $key => $value)
                    @if(is_array($value))
                        @foreach($value as $innerValue)
                            <input type="hidden" name="{{ $key }}[]" value="{{ $innerValue }}">
                        @endforeach
                    @else
                        <input type="hidden" name="{{ $key }}" value="{{ $value }}">
                    @endif
                @endforeach
                <select class="form-select" name="orden" aria-label="Ordenar productos" onchange="this.form.submit()">
                    <option value="destacados" @selected(request('orden', 'destacados') === 'destacados')>Destacados</option>
                    <option value="recientes" @selected(request('orden') === 'recientes')>Mas recientes</option>
                    <option value="precio_asc" @selected(request('orden') === 'precio_asc')>Menor precio</option>
                    <option value="precio_desc" @selected(request('orden') === 'precio_desc')>Mayor precio</option>
                </select>
            </form>
        </div>
    </div>

    <div class="row g-4">
        <aside class="col-lg-3 d-none d-lg-block">
            @include('shop.partials.filters')
        </aside>
        <div class="col-lg-9">
            @if(request()->hasAny(['q', 'categoria', 'marca', 'precio_min', 'precio_max', 'oferta']))
                <div class="d-flex flex-wrap align-items-center gap-2 mb-3">
                    <span class="small text-muted">Filtros activos</span>
                    @if(request('q'))
                        <span class="badge text-bg-light border">Busqueda: {{ request('q') }}</span>
                    @endif
                    @foreach($selectedCategories as $selectedCategory)
                        <span class="badge text-bg-light border">Categoria: {{ $selectedCategory->name }}</span>
                    @endforeach
                    @foreach($selectedBrands as $selectedBrand)
                        <span class="badge text-bg-light border">Marca: {{ $selectedBrand->name }}</span>
                    @endforeach
                    @if(request()->boolean('oferta'))
                        <span class="badge text-bg-light border">Ofertas</span>
                    @endif
                    <a class="small fw-bold text-vn-green" href="{{ route('shop.catalog') }}">Limpiar</a>
                </div>
            @endif

            <div class="row g-3 g-xl-4">
                @forelse($products as $product)
                    <div class="col-6 col-md-4">
                        <x-shop.product-card :product="$product" />
                    </div>
                @empty
                    <div class="col-12">
                        <div class="checkout-card p-5 text-center">
                            <i class="bi bi-search fs-1 text-vn-green"></i>
                            <h2 class="h5 fw-black mt-3">No encontramos productos</h2>
                            <p class="text-muted mb-3">Prueba con otros filtros o limpia la busqueda.</p>
                            <a class="btn btn-vn-outline" href="{{ route('shop.catalog') }}">Ver todo el catalogo</a>
                        </div>
                    </div>
                @endforelse
            </div>

            <div class="mt-4">
                {{ $products->links() }}
            </div>
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
