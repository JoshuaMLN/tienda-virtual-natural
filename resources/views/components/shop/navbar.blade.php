<div class="top-shipping-bar py-2 text-center">
    <i class="bi bi-truck"></i> Envio gratis a todo el Peru por compras desde S/ 149
</div>

<header class="shop-header">
    <div class="container py-3">
        <div class="d-flex flex-wrap align-items-center gap-3">
            <a class="brand-mark me-auto" href="{{ route('shop.index') }}">
                <span class="brand-leaf"><i class="bi bi-leaf-fill"></i></span>
                <span>VitaNatural <span class="brand-subtitle">Bienestar que se nota</span></span>
            </a>

            <form class="search-shell flex-grow-1" action="{{ route('shop.catalog') }}">
                <div class="input-group">
                    <input class="form-control" type="search" placeholder="Buscar productos, marcas y mas...">
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
                <a class="header-action" href="{{ route('shop.cart') }}" aria-label="Carrito">
                    <i class="bi bi-cart3"></i><span class="d-none d-md-inline">Carrito</span><span class="cart-count">3</span>
                </a>
            </div>
        </div>
    </div>

    <nav class="category-nav bg-white">
        <div class="container d-flex flex-nowrap gap-4 overflow-auto py-2">
            <a href="{{ route('shop.catalog') }}"><i class="bi bi-list"></i> Todas las categorias</a>
            <a href="{{ route('shop.catalog') }}">Vitaminas</a>
            <a href="{{ route('shop.catalog') }}">Suplementos</a>
            <a href="{{ route('shop.catalog') }}">Snacks saludables</a>
            <a href="{{ route('shop.catalog') }}">Superfoods</a>
            <a href="{{ route('shop.catalog') }}">Belleza natural</a>
            <a class="offer-link" href="{{ route('shop.catalog') }}">Ofertas</a>
        </div>
    </nav>
</header>
