@props(['product' => []])

@php
    $product = array_merge([
        'name' => 'Producto natural',
        'description' => 'Presentacion 120 capsulas',
        'price' => 'S/ 79.90',
        'old_price' => null,
        'rating' => 5,
        'reviews' => 73,
        'image' => 'https://images.unsplash.com/photo-1608571423902-eed4a5ad8108?auto=format&fit=crop&w=700&q=80',
        'url' => route('shop.product'),
        'badge' => null,
    ], $product);
@endphp

<article class="card product-card">
    <a href="{{ $product['url'] }}">
        <div class="position-relative">
            <div class="product-image" style="background-image: url('{{ $product['image'] }}')"></div>
            @if($product['badge'])
                <span class="badge text-bg-warning position-absolute top-0 start-0 m-2">{{ $product['badge'] }}</span>
            @endif
            <button class="btn btn-light btn-sm position-absolute top-0 end-0 m-2 rounded-circle" type="button" aria-label="Agregar a favoritos">
                <i class="bi bi-heart"></i>
            </button>
        </div>
    </a>
    <div class="card-body d-flex flex-column">
        <a class="product-title" href="{{ $product['url'] }}">{{ $product['name'] }}</a>
        <p class="small text-muted mb-2">{{ $product['description'] }}</p>
        <div class="rating mb-2" aria-label="{{ $product['rating'] }} estrellas">
            @for($i = 1; $i <= 5; $i++)
                <i class="bi {{ $i <= $product['rating'] ? 'bi-star-fill' : 'bi-star' }}"></i>
            @endfor
            <span class="text-muted">({{ $product['reviews'] }})</span>
        </div>
        <div class="d-flex align-items-end gap-2 mt-auto">
            <span class="price">{{ $product['price'] }}</span>
            @if($product['old_price'])
                <span class="old-price">{{ $product['old_price'] }}</span>
            @endif
        </div>
    </div>
</article>
