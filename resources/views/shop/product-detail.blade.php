@extends('layouts.shop')

@section('title', 'Omega 3 Premium | VitaNatural')

@section('content')
@php
    $productImages = [
        'https://images.unsplash.com/photo-1587854692152-cbe660dbde88?auto=format&fit=crop&w=900&q=80',
        'https://images.unsplash.com/photo-1471864190281-a93a3070b6de?auto=format&fit=crop&w=900&q=80',
        'https://images.unsplash.com/photo-1550572017-edd951aa8f72?auto=format&fit=crop&w=900&q=80',
    ];
    $related = [
        ['name' => 'Vitamina D3 5000 UI', 'description' => '120 capsulas', 'price' => 'S/ 54.90', 'rating' => 5, 'reviews' => 54, 'image' => 'https://images.unsplash.com/photo-1587854692152-cbe660dbde88?auto=format&fit=crop&w=700&q=80'],
        ['name' => 'Magnesio citrato', 'description' => '120 capsulas', 'price' => 'S/ 49.90', 'rating' => 5, 'reviews' => 49, 'image' => 'https://images.unsplash.com/photo-1471864190281-a93a3070b6de?auto=format&fit=crop&w=700&q=80'],
        ['name' => 'Aceite de krill', 'description' => '60 capsulas', 'price' => 'S/ 69.90', 'rating' => 4, 'reviews' => 18, 'image' => 'https://images.unsplash.com/photo-1628771065518-0d82f1938462?auto=format&fit=crop&w=700&q=80'],
        ['name' => 'Coenzima Q10', 'description' => '60 capsulas', 'price' => 'S/ 69.90', 'rating' => 4, 'reviews' => 29, 'image' => 'https://images.unsplash.com/photo-1550572017-edd951aa8f72?auto=format&fit=crop&w=700&q=80'],
    ];
@endphp

<section class="container py-4">
    <nav class="small text-muted mb-3">Inicio &gt; Suplementos &gt; Omega 3 Premium</nav>
    <div class="row g-4">
        <div class="col-lg-6">
            <div class="row g-3">
                <div class="col-2">
                    <div class="d-grid gap-2">
                        @foreach($productImages as $image)
                            <button class="border rounded-2 bg-white p-1" type="button">
                                <div class="thumb-sm w-100" style="height: 76px; background-image: url('{{ $image }}')"></div>
                            </button>
                        @endforeach
                        <button class="btn btn-light border" type="button" aria-label="Ver video"><i class="bi bi-play-circle-fill text-warning fs-3"></i></button>
                    </div>
                </div>
                <div class="col-10">
                    <div class="product-image rounded-2 border" style="aspect-ratio: 1 / 1; background-image: url('{{ $productImages[0] }}')"></div>
                </div>
            </div>
        </div>
        <div class="col-lg-6">
            <h1 class="section-title mb-1">Omega 3 Premium</h1>
            <p class="text-muted">120 capsulas</p>
            <div class="rating mb-2">
                <i class="bi bi-star-fill"></i><i class="bi bi-star-fill"></i><i class="bi bi-star-fill"></i><i class="bi bi-star-fill"></i><i class="bi bi-star-half"></i>
                <span class="text-muted">(73 opiniones)</span>
            </div>
            <div class="d-flex align-items-end gap-2 mb-2">
                <span class="display-6 fw-black text-vn-green">S/ 79.90</span>
                <span class="old-price">S/ 89.90</span>
            </div>
            <p class="small text-success fw-bold"><i class="bi bi-check-circle"></i> En stock</p>
            <p>Aceite de pescado de alta pureza. Apoya la salud del corazon, cerebro y articulaciones.</p>

            <div class="row g-3 text-center my-4">
                <div class="col-4"><i class="bi bi-heart-pulse text-vn-green fs-3"></i><br><span class="small">Apoya tu salud cardiovascular</span></div>
                <div class="col-4"><i class="bi bi-brightness-high text-vn-green fs-3"></i><br><span class="small">Mejora el bienestar</span></div>
                <div class="col-4"><i class="bi bi-bicycle text-vn-green fs-3"></i><br><span class="small">Apoyo articular</span></div>
            </div>

            <label class="form-label fw-bold">Cantidad</label>
            <div class="d-flex flex-wrap gap-3 align-items-center mb-3">
                <div class="quantity-control">
                    <button data-quantity="minus" type="button">-</button>
                    <input type="number" value="1" min="1">
                    <button data-quantity="plus" type="button">+</button>
                </div>
                <button class="btn btn-vn btn-lg flex-grow-1" type="button"><i class="bi bi-cart-plus me-2"></i>Anadir al carrito</button>
            </div>
            <button class="btn btn-vn-outline w-100" type="button"><i class="bi bi-heart me-2"></i>Anadir a favoritos</button>

            <div class="mt-4">
                <x-shop.trust-badges />
            </div>
        </div>
    </div>

    <div class="checkout-card mt-5">
        <ul class="nav nav-tabs px-3 pt-3" role="tablist">
            <li class="nav-item"><button class="nav-link active" data-bs-toggle="tab" data-bs-target="#description" type="button">Descripcion</button></li>
            <li class="nav-item"><button class="nav-link" data-bs-toggle="tab" data-bs-target="#benefits" type="button">Beneficios</button></li>
            <li class="nav-item"><button class="nav-link" data-bs-toggle="tab" data-bs-target="#ingredients" type="button">Ingredientes</button></li>
            <li class="nav-item"><button class="nav-link" data-bs-toggle="tab" data-bs-target="#usage" type="button">Modo de uso</button></li>
        </ul>
        <div class="tab-content p-4">
            <div class="tab-pane fade show active" id="description">
                <p>Omega 3 Premium contiene EPA y DHA de alta concentracion que contribuyen al funcionamiento normal del corazon y cerebro.</p>
                <ul>
                    <li>Ayuda al equilibrio cardiovascular.</li>
                    <li>Contribuye al bienestar cerebral.</li>
                    <li>Formula de buena absorcion.</li>
                </ul>
            </div>
            <div class="tab-pane fade" id="benefits">Apoya una rutina diaria de bienestar, especialmente en personas con baja ingesta de pescado.</div>
            <div class="tab-pane fade" id="ingredients">Aceite de pescado purificado, gelatina, glicerina y agua purificada.</div>
            <div class="tab-pane fade" id="usage">Tomar 1 capsula al dia con alimentos, salvo indicacion profesional.</div>
        </div>
    </div>

    <section class="mt-5">
        <h2 class="section-title mb-3">Productos relacionados</h2>
        <div class="row g-3 g-lg-4">
            @foreach($related as $product)
                <div class="col-6 col-md-3">
                    <x-shop.product-card :product="$product" />
                </div>
            @endforeach
        </div>
    </section>
</section>
@endsection
