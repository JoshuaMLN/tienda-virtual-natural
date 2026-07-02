@extends('layouts.shop')

@section('title', 'Inicio | VitaNatural')

@section('content')
<section class="hero-panel d-flex align-items-center">
    <div class="container py-5">
        <div class="hero-copy">
            <h1>Vive bien,<br>vive natural</h1>
            <p class="lead text-muted my-4">Productos organicos y saludables para una vida plena, seleccionados con origen responsable.</p>
            <a class="btn btn-vn btn-lg" href="{{ route('shop.catalog') }}">Ver productos</a>
        </div>
    </div>
</section>

<section class="container py-5">
    <div class="d-flex justify-content-between align-items-end mb-3">
        <h2 class="section-title mb-0">Categorias destacadas</h2>
        <a class="small fw-bold text-vn-green" href="{{ route('shop.catalog') }}">Ver todas</a>
    </div>
    <div class="row g-3">
        @forelse($categories as $category)
            <div class="col-6 col-md-3 col-lg-2">
                <x-shop.category-card
                    :title="$category->name"
                    :icon="$category->icon_class ?? 'bi-grid'"
                    :url="route('shop.catalog', ['categoria' => $category->slug])"
                />
            </div>
        @empty
            <div class="col-12">
                <div class="alert alert-light border mb-0">Aun no hay categorias destacadas.</div>
            </div>
        @endforelse
    </div>
</section>

<section class="container pb-5">
    <div class="d-flex justify-content-between align-items-end mb-3">
        <h2 class="section-title mb-0">Productos destacados</h2>
        <a class="small fw-bold text-vn-green" href="{{ route('shop.catalog') }}">Ver todos</a>
    </div>
    <div class="row g-3 g-lg-4">
        @forelse($featuredProducts as $product)
            <div class="col-6 col-md-4 col-lg-2">
                <x-shop.product-card :product="$product" />
            </div>
        @empty
            <div class="col-12">
                <div class="alert alert-light border mb-0">Aun no hay productos destacados.</div>
            </div>
        @endforelse
    </div>
</section>

<section class="container pb-5">
    <div class="row g-3">
        <div class="col-md-4">
            <div class="promo-tile p-4 d-flex align-items-center justify-content-between">
                <div><strong>Envio gratis a todo el Peru</strong><span class="small text-muted">Por compras desde S/ 149</span></div>
                <i class="bi bi-truck fs-1 text-vn-green"></i>
            </div>
        </div>
        <div class="col-md-4">
            <div class="promo-tile p-4">
                <strong class="text-warning">15% dscto.</strong>
                <span class="small text-muted">En tu primera compra con el cupon VITANATURALS</span>
            </div>
        </div>
        <div class="col-md-4">
            <div class="promo-tile p-4 d-flex align-items-center justify-content-between">
                <div><strong>Acumula puntos</strong><span class="small text-muted">Con cada compra y obten beneficios</span></div>
                <i class="bi bi-flower1 fs-1 text-vn-green"></i>
            </div>
        </div>
    </div>
</section>

<section class="container pb-5">
    <h2 class="section-title text-center mb-4">Por que elegir VitaNatural</h2>
    <div class="row g-3 text-center">
        @foreach([
            ['bi-patch-check', 'Productos 100% naturales'],
            ['bi-shield-check', 'Calidad garantizada'],
            ['bi-award', 'Hecho en Peru'],
            ['bi-truck', 'Envios rapidos'],
            ['bi-people', 'Asesoria personalizada'],
        ] as [$icon, $text])
            <div class="col-6 col-md">
                <div class="category-icon mx-auto mb-2"><i class="bi {{ $icon }}"></i></div>
                <strong class="small">{{ $text }}</strong>
            </div>
        @endforeach
    </div>
</section>

<section class="container pb-5">
    <h2 class="section-title text-center mb-4">Marcas que confian en nosotros</h2>
    <div class="row g-3 align-items-center text-center text-muted fw-black">
        @forelse($brands as $brand)
            <div class="col-6 col-md-2">
                @if($brand->logo_source)
                    <img class="img-fluid" src="{{ $brand->logo_source }}" alt="{{ $brand->name }}" style="max-height: 42px;">
                @else
                    {{ $brand->name }}
                @endif
            </div>
        @empty
            <div class="col-12">Pronto tendremos marcas disponibles.</div>
        @endforelse
    </div>
    <div class="d-flex flex-wrap justify-content-center gap-2 mt-4">
        <span class="payment-logo">VISA</span>
        <span class="payment-logo">Mastercard</span>
        <span class="payment-logo">AMEX</span>
        <span class="payment-logo">Diners</span>
        <span class="payment-logo">Yape</span>
        <span class="payment-logo">Plin</span>
    </div>
</section>

<section class="container pb-4">
    <x-shop.trust-badges />
</section>
@endsection
