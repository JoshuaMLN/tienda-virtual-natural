@props(['active' => 'profile'])

@php
    $items = [
        'profile' => ['label' => 'Mi perfil', 'icon' => 'bi-person', 'route' => 'account.profile'],
        'security' => ['label' => 'Seguridad', 'icon' => 'bi-shield-lock', 'route' => 'account.security'],
        'orders' => ['label' => 'Mis pedidos', 'icon' => 'bi-bag', 'route' => 'account.orders'],
        'addresses' => ['label' => 'Direcciones', 'icon' => 'bi-geo-alt', 'route' => 'account.addresses'],
    ];
    $activeItem = $items[$active] ?? $items['profile'];
@endphp

<aside class="account-sidebar checkout-card p-2 align-self-start d-none d-lg-block" aria-label="Navegacion de mi cuenta">
    @foreach($items as $key => $item)
        <a class="{{ $active === $key ? 'active' : '' }}" href="{{ route($item['route']) }}" @if($active === $key) aria-current="page" @endif>
            <i class="bi {{ $item['icon'] }}" aria-hidden="true"></i> {{ $item['label'] }}
        </a>
    @endforeach
    <div class="account-sidebar-logout-wrap">
        <button
            class="account-sidebar-action account-sidebar-logout"
            type="button"
            data-bs-toggle="modal"
            data-bs-target="#logoutConfirmationModal"
        >
            <i class="bi bi-box-arrow-right" aria-hidden="true"></i> Cerrar sesion
        </button>
    </div>
</aside>

<div class="account-mobile-navigation d-lg-none">
    <div class="account-mobile-current min-w-0">
        <span>Mi cuenta</span>
        <strong class="text-truncate">
            <i class="bi {{ $activeItem['icon'] }}" aria-hidden="true"></i> {{ $activeItem['label'] }}
        </strong>
    </div>
    <button
        class="account-mobile-menu-button"
        type="button"
        data-bs-toggle="offcanvas"
        data-bs-target="#accountNavigationOffcanvas"
        aria-controls="accountNavigationOffcanvas"
        aria-label="Abrir menu de mi cuenta"
    >
        <i class="bi bi-list" aria-hidden="true"></i>
    </button>
</div>

<div
    class="offcanvas offcanvas-start account-mobile-offcanvas"
    tabindex="-1"
    id="accountNavigationOffcanvas"
    aria-labelledby="accountNavigationOffcanvasLabel"
>
    <div class="offcanvas-header">
        <div>
            <span class="account-mobile-eyebrow">Mi cuenta</span>
            <h2 class="offcanvas-title h5 fw-black mb-0" id="accountNavigationOffcanvasLabel">Navegacion</h2>
        </div>
        <button class="btn-close" type="button" data-bs-dismiss="offcanvas" aria-label="Cerrar"></button>
    </div>
    <div class="offcanvas-body d-flex flex-column p-0">
        <div class="account-mobile-identity">
            <i class="bi bi-person-circle" aria-hidden="true"></i>
            <div class="min-w-0">
                <div class="fw-black text-truncate">{{ auth()->user()->name }}</div>
                <div class="small text-muted text-truncate">{{ auth()->user()->email }}</div>
            </div>
        </div>

        <nav class="account-mobile-links" aria-label="Opciones de mi cuenta">
            @foreach($items as $key => $item)
                <a class="account-mobile-link {{ $active === $key ? 'active' : '' }}" href="{{ route($item['route']) }}" @if($active === $key) aria-current="page" @endif>
                    <i class="bi {{ $item['icon'] }}" aria-hidden="true"></i>
                    <span>{{ $item['label'] }}</span>
                    <i class="bi bi-chevron-right ms-auto" aria-hidden="true"></i>
                </a>
            @endforeach
        </nav>

        <div class="account-mobile-logout-wrap mt-auto">
            <button class="account-mobile-logout" type="button" data-account-mobile-logout>
                <i class="bi bi-box-arrow-right" aria-hidden="true"></i> Cerrar sesion
            </button>
        </div>
    </div>
</div>
