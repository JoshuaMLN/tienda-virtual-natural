<div class="top-shipping-bar py-2 text-center">
    <i class="bi bi-truck"></i> Envio gratis a todo el Peru por compras desde S/ 149
</div>

<header class="shop-header">
    <div class="container py-3">
        <div class="d-flex flex-wrap align-items-center gap-3">
            <a class="brand-mark me-auto" href="{{ route('shop.index') }}">
                <span class="brand-leaf"><i class="bi bi-flower1"></i></span>
                <span>VitaNatural <span class="brand-subtitle">Bienestar que se nota</span></span>
            </a>

            <form class="search-shell flex-grow-1" action="{{ route('shop.catalog') }}">
                <div class="input-group">
                    <input class="form-control" name="q" type="search" value="{{ request('q') }}" placeholder="Buscar productos, marcas y mas...">
                    <button class="btn btn-green" type="submit" aria-label="Buscar">
                        <i class="bi bi-search"></i>
                    </button>
                </div>
            </form>

            <div class="d-flex align-items-center gap-3 ms-auto">
                <a class="header-action d-none d-lg-inline-flex" href="https://wa.me/51987654321">
                    <i class="bi bi-whatsapp"></i><span>WhatsApp<br>987 654 321</span>
                </a>
                <a class="header-action" href="{{ route('login') }}" aria-label="Mi cuenta">
                    <i class="bi bi-person"></i><span class="d-none d-md-inline">Mi cuenta</span>
                </a>
                <a class="header-action d-none d-md-inline-flex" href="#" aria-label="Favoritos">
                    <i class="bi bi-heart"></i><span>Favoritos</span>
                </a>
                <button
                    class="header-action header-action-button"
                    type="button"
                    data-bs-toggle="offcanvas"
                    data-bs-target="#cartDrawer"
                    aria-controls="cartDrawer"
                    aria-label="Abrir carrito"
                >
                    <i class="bi bi-cart3"></i><span class="d-none d-md-inline">Carrito</span><span class="cart-count" data-cart-count data-cart-info-url="{{ route('shop.cart.info') }}">{{ $cartCount }}</span>
                </button>
            </div>
        </div>
    </div>

    <nav class="category-nav bg-white" data-category-menu>
        <div class="container py-2">
            <div class="category-nav-row">
                <a class="category-nav-all d-none d-lg-inline-flex" href="{{ route('shop.catalog') }}">
                    <i class="bi bi-list"></i> Todas las categorias
                </a>

                <button
                    class="category-nav-toggle d-lg-none"
                    type="button"
                    data-category-menu-toggle
                    aria-controls="mobileCategoryMenu"
                    aria-expanded="false"
                >
                    <span><i class="bi bi-list"></i> Todas las categorias</span>
                    <i class="bi bi-chevron-down"></i>
                </button>

                <div class="category-nav-links d-none d-lg-flex">
                    @foreach($navigationCategories as $navigationCategory)
                        <a href="{{ route('shop.catalog', ['categoria' => $navigationCategory->slug]) }}">
                            <i class="bi {{ $navigationCategory->icon_class ?? 'bi-grid' }}"></i>
                            {{ $navigationCategory->name }}
                        </a>
                    @endforeach
                    <a class="offer-link" href="{{ route('shop.catalog', ['oferta' => 1]) }}">
                        <i class="bi bi-tag"></i>
                        Ofertas
                    </a>
                </div>
            </div>

            <div class="category-nav-menu d-lg-none" id="mobileCategoryMenu" data-category-menu-panel hidden>
                <div class="category-nav-menu-grid">
                    <a href="{{ route('shop.catalog') }}">
                        <i class="bi bi-grid"></i>
                        Todas
                    </a>
                    @foreach($navigationCategories as $navigationCategory)
                        <a href="{{ route('shop.catalog', ['categoria' => $navigationCategory->slug]) }}">
                            <i class="bi {{ $navigationCategory->icon_class ?? 'bi-grid' }}"></i>
                            {{ $navigationCategory->name }}
                        </a>
                    @endforeach
                    <a class="offer-link" href="{{ route('shop.catalog', ['oferta' => 1]) }}">
                        <i class="bi bi-tag"></i>
                        Ofertas
                    </a>
                </div>
            </div>
        </div>
    </nav>
</header>
