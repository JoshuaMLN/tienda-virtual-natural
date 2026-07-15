@props(['active' => 'profile'])

<aside class="account-sidebar checkout-card p-2 align-self-start">
    <a class="{{ $active === 'profile' ? 'active' : '' }}" href="{{ route('account.profile') }}"><i class="bi bi-person"></i> Mi perfil</a>
    <a class="{{ $active === 'security' ? 'active' : '' }}" href="{{ route('account.security') }}"><i class="bi bi-shield-lock"></i> Seguridad</a>
    <a class="{{ $active === 'orders' ? 'active' : '' }}" href="{{ route('account.orders') }}"><i class="bi bi-bag"></i> Mis pedidos</a>
    <a class="{{ $active === 'addresses' ? 'active' : '' }}" href="{{ route('account.addresses') }}"><i class="bi bi-geo-alt"></i> Direcciones</a>
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
