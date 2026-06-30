@props(['active' => 'profile'])

<aside class="account-sidebar checkout-card p-2 align-self-start">
    <a class="{{ $active === 'profile' ? 'active' : '' }}" href="{{ route('account.profile') }}"><i class="bi bi-person"></i> Mi perfil</a>
    <a class="{{ $active === 'orders' ? 'active' : '' }}" href="{{ route('account.orders') }}"><i class="bi bi-bag"></i> Mis pedidos</a>
    <a class="{{ $active === 'addresses' ? 'active' : '' }}" href="{{ route('account.addresses') }}"><i class="bi bi-geo-alt"></i> Direcciones</a>
    <a href="#"><i class="bi bi-credit-card"></i> Metodos de pago</a>
    <a href="#"><i class="bi bi-heart"></i> Lista de deseos</a>
    <a href="#"><i class="bi bi-bell"></i> Notificaciones</a>
    <a href="{{ route('login') }}"><i class="bi bi-box-arrow-right"></i> Cerrar sesion</a>
</aside>
