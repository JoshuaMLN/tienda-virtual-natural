<div class="top-shipping-bar py-2 text-center">
    <i class="bi bi-truck"></i> {{ $storeSettings->shippingBanner() }}
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
                <a class="header-action d-none d-lg-inline-flex" href="{{ $storeSettings->whatsappUrl() }}" target="_blank" rel="noopener noreferrer">
                    <i class="bi bi-whatsapp"></i><span>WhatsApp<br>{{ $storeSettings->whatsappDisplay() }}</span>
                </a>
                @guest
                    <a class="header-action" href="{{ route('login') }}" aria-label="Iniciar sesion">
                        <i class="bi bi-person"></i><span class="d-none d-md-inline">Iniciar sesion</span>
                    </a>
                @else
                    @if(auth()->user()->isAdmin())
                        <a class="header-action" href="{{ route('admin.dashboard') }}" aria-label="Ir al panel administrativo">
                            <span class="header-account-avatar header-account-initials" aria-hidden="true">{{ auth()->user()->initials }}</span>
                            <span class="d-none d-md-inline">Panel admin</span>
                        </a>
                    @else
                    <div class="dropdown">
                        <button
                            class="header-action header-action-button header-account-trigger dropdown-toggle"
                            type="button"
                            data-bs-toggle="dropdown"
                            aria-expanded="false"
                            aria-label="Abrir menu de mi cuenta"
                        >
                            @if(auth()->user()->avatar_url)
                                <img
                                    class="header-account-avatar"
                                    src="{{ auth()->user()->avatar_url }}"
                                    alt=""
                                    width="30"
                                    height="30"
                                >
                            @else
                                <span class="header-account-avatar header-account-initials" aria-hidden="true">
                                    {{ auth()->user()->initials }}
                                </span>
                            @endif
                            <span class="d-none d-md-inline">Mi cuenta</span>
                        </button>
                        <ul class="dropdown-menu dropdown-menu-end account-dropdown-menu">
                            <li class="account-dropdown-identity">
                                <strong class="d-block text-truncate">{{ auth()->user()->name }}</strong>
                                <span class="d-block small text-muted text-truncate">{{ auth()->user()->email }}</span>
                            </li>
                            <li><hr class="dropdown-divider"></li>
                            <li><a class="dropdown-item" href="{{ route('account.profile') }}"><i class="bi bi-person"></i> Mi perfil</a></li>
                            <li><a class="dropdown-item" href="{{ route('account.orders') }}"><i class="bi bi-bag"></i> Mis pedidos</a></li>
                            <li><a class="dropdown-item" href="{{ route('account.addresses') }}"><i class="bi bi-geo-alt"></i> Direcciones</a></li>
                            <li><a class="dropdown-item" href="{{ route('account.security') }}"><i class="bi bi-shield-lock"></i> Seguridad</a></li>
                            @unless(auth()->user()->hasVerifiedEmail())
                                <li><a class="dropdown-item account-verification-link" href="{{ route('verification.notice') }}"><i class="bi bi-envelope-exclamation"></i> Verificar correo</a></li>
                            @endunless
                            <li><hr class="dropdown-divider"></li>
                            <li>
                                <button
                                    class="dropdown-item account-dropdown-logout"
                                    type="button"
                                    data-bs-toggle="modal"
                                    data-bs-target="#logoutConfirmationModal"
                                >
                                    <i class="bi bi-box-arrow-right"></i> Cerrar sesion
                                </button>
                            </li>
                        </ul>
                    </div>
                    @endif
                @endguest
                @if(! auth()->check() || auth()->user()->isCustomer())
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
                @endif
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
