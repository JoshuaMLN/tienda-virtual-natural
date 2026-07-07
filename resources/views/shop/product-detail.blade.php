@extends('layouts.shop')

@section('title', $product->name.' | VitaNatural')

@section('content')
@php
    $galleryImages = $product->images->where('is_primary', false);
    $productImages = collect([(object) [
        'url' => $product->main_image_url,
        'alt_text' => $product->primaryImage?->alt_text ?: $product->name,
    ]])->merge($galleryImages);
    $firstImage = $productImages->first();
@endphp

<section class="container py-4">
    <nav class="small text-muted mb-3">
        Inicio &gt;
        <a href="{{ route('shop.catalog', ['categoria' => $product->category->slug]) }}">{{ $product->category->name }}</a> &gt;
        {{ $product->name }}
    </nav>
    <div class="row g-4">
        <div class="col-lg-6">
            <div class="row g-3" data-product-gallery>
                <div class="col-2">
                    <div class="d-grid gap-2">
                        @foreach($productImages as $imageIndex => $image)
                            <button
                                class="product-gallery-thumb border rounded-2 bg-white p-1 {{ $imageIndex === 0 ? 'is-active' : '' }}"
                                type="button"
                                data-product-gallery-thumb
                                data-image-url="{{ $image->url }}"
                                data-image-alt="{{ $image->alt_text ?: $product->name }}"
                                aria-label="Ver imagen {{ $imageIndex + 1 }} de {{ $product->name }}"
                                aria-pressed="{{ $imageIndex === 0 ? 'true' : 'false' }}"
                            >
                                <div class="thumb-sm w-100" style="height: 76px; background-image: url('{{ $image->url }}')"></div>
                            </button>
                        @endforeach
                    </div>
                </div>
                <div class="col-10">
                    <button
                        class="product-detail-image-button"
                        type="button"
                        data-product-gallery-open
                        data-image-url="{{ $firstImage->url }}"
                        data-image-alt="{{ $firstImage->alt_text ?: $product->name }}"
                        aria-label="Ampliar imagen de {{ $product->name }}"
                    >
                        <span
                            class="product-image rounded-2 border"
                            data-product-gallery-main
                            style="aspect-ratio: 1 / 1; background-image: url('{{ $firstImage->url }}')"
                        ></span>
                    </button>
                </div>
            </div>
        </div>
        <div class="col-lg-6">
            <h1 class="section-title mb-1">{{ $product->name }}</h1>
            <p class="text-muted">{{ $product->short_description }} @if($product->brand) - {{ $product->brand->name }} @endif</p>
            <div class="rating mb-2">
                @for($i = 1; $i <= 5; $i++)
                    <i class="bi {{ $i <= round((float) $product->rating_average) ? 'bi-star-fill' : 'bi-star' }}"></i>
                @endfor
                <span class="text-muted">({{ $product->reviews_count }} opiniones)</span>
            </div>
            <div class="d-flex align-items-end gap-2 mb-2">
                <span class="display-6 fw-black text-vn-green">{{ $product->formatted_price }}</span>
                @if($product->formatted_compare_at_price)
                    <span class="old-price">{{ $product->formatted_compare_at_price }}</span>
                @endif
            </div>
            <p class="small {{ $product->public_stock_text_class }} fw-bold">
                <i class="bi {{ $product->public_stock_icon }}"></i>
                {{ $product->public_stock_label }}
            </p>
            <p>{{ $product->description }}</p>

            <div class="row g-3 text-center my-4">
                <div class="col-4"><i class="bi bi-heart-pulse text-vn-green fs-3"></i><br><span class="small">Bienestar diario</span></div>
                <div class="col-4"><i class="bi bi-brightness-high text-vn-green fs-3"></i><br><span class="small">Calidad seleccionada</span></div>
                <div class="col-4"><i class="bi bi-bicycle text-vn-green fs-3"></i><br><span class="small">Rutina saludable</span></div>
            </div>

            <label class="form-label fw-bold">Cantidad</label>
            <div class="d-flex flex-wrap gap-3 align-items-center mb-3">
                <div class="quantity-control">
                    <button data-quantity="minus" type="button" @disabled(! $product->is_in_stock)>-</button>
                    <input type="number" value="1" min="1" max="{{ max(1, $product->stock) }}" @disabled(! $product->is_in_stock)>
                    <button data-quantity="plus" type="button" @disabled(! $product->is_in_stock)>+</button>
                </div>
                <button class="btn btn-vn btn-lg flex-grow-1" type="button" @disabled(! $product->is_in_stock)>
                    <i class="bi bi-cart-plus me-2"></i>{{ $product->is_in_stock ? 'Anadir al carrito' : 'No disponible' }}
                </button>
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
            <div class="tab-pane fade show active" id="description">{{ $product->description }}</div>
            <div class="tab-pane fade" id="benefits">{{ $product->benefits ?: 'Pronto agregaremos beneficios detallados.' }}</div>
            <div class="tab-pane fade" id="ingredients">{{ $product->ingredients ?: 'Pronto agregaremos ingredientes detallados.' }}</div>
            <div class="tab-pane fade" id="usage">{{ $product->usage_instructions ?: 'Sigue las indicaciones del empaque.' }}</div>
        </div>
    </div>

    <section class="mt-5">
        <h2 class="section-title mb-3">Productos relacionados</h2>
        <div class="row g-3 g-lg-4">
            @forelse($relatedProducts as $relatedProduct)
                <div class="col-6 col-md-3">
                    <x-shop.product-card :product="$relatedProduct" />
                </div>
            @empty
                <div class="col-12">
                    <div class="alert alert-light border mb-0">No hay productos relacionados por ahora.</div>
                </div>
            @endforelse
        </div>
    </section>
</section>

<div class="modal fade" id="productImageModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-xl">
        <div class="modal-content bg-transparent border-0">
            <button class="btn btn-light align-self-end mb-2" type="button" data-bs-dismiss="modal" aria-label="Cerrar">
                <i class="bi bi-x-lg"></i>
            </button>
            <img class="img-fluid rounded-2 bg-white" src="{{ $firstImage->url }}" alt="{{ $firstImage->alt_text ?: $product->name }}" data-product-gallery-modal-image>
        </div>
    </div>
</div>
@endsection
