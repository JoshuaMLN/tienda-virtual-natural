@props(['product' => []])

@php
    $slug = data_get($product, 'slug');
    $compareAtPrice = data_get($product, 'formatted_compare_at_price') ?? data_get($product, 'old_price');
    $price = data_get($product, 'formatted_price') ?? data_get($product, 'price', 'S/ 79.90');

    if (is_numeric($price)) {
        $price = 'S/ '.number_format((float) $price, 2);
    }

    $productView = [
        'name' => data_get($product, 'name', 'Producto natural'),
        'description' => data_get($product, 'short_description') ?? data_get($product, 'description', 'Presentacion 120 capsulas'),
        'price' => $price,
        'old_price' => $compareAtPrice,
        'rating' => (int) round((float) (data_get($product, 'rating_average') ?? data_get($product, 'rating', 5))),
        'reviews' => data_get($product, 'reviews_count') ?? data_get($product, 'reviews', 73),
        'image' => data_get($product, 'main_image_url') ?? data_get($product, 'image', asset(\App\Models\Product::DEFAULT_IMAGE)),
        'url' => data_get($product, 'url') ?? ($slug ? route('shop.product', $slug) : route('shop.catalog')),
        'badge' => data_get($product, 'badge') ?? ($compareAtPrice ? 'Oferta' : null),
    ];
@endphp

<article class="card product-card">
    <a href="{{ $productView['url'] }}">
        <div class="position-relative">
            <div class="product-image" style="background-image: url('{{ $productView['image'] }}')"></div>
            @if($productView['badge'])
                <span class="badge text-bg-warning position-absolute top-0 start-0 m-2">{{ $productView['badge'] }}</span>
            @endif
            <button class="btn btn-light btn-sm position-absolute top-0 end-0 m-2 rounded-circle" type="button" aria-label="Agregar a favoritos">
                <i class="bi bi-heart"></i>
            </button>
        </div>
    </a>
    <div class="card-body d-flex flex-column">
        <a class="product-title" href="{{ $productView['url'] }}">{{ $productView['name'] }}</a>
        <p class="small text-muted mb-2">{{ $productView['description'] }}</p>
        <div class="rating mb-2" aria-label="{{ $productView['rating'] }} estrellas">
            @for($i = 1; $i <= 5; $i++)
                <i class="bi {{ $i <= $productView['rating'] ? 'bi-star-fill' : 'bi-star' }}"></i>
            @endfor
            <span class="text-muted">({{ $productView['reviews'] }})</span>
        </div>
        <div class="d-flex align-items-end gap-2 mt-auto">
            <span class="price">{{ $productView['price'] }}</span>
            @if($productView['old_price'])
                <span class="old-price">{{ $productView['old_price'] }}</span>
            @endif
        </div>
    </div>
</article>
