@props(['icon' => 'bi-grid', 'title' => 'Categoria', 'url' => null])

<a class="category-card soft-card" href="{{ $url ?? route('shop.catalog') }}">
    <span class="category-icon"><i class="bi {{ $icon }}"></i></span>
    <strong class="small">{{ $title }}</strong>
</a>
