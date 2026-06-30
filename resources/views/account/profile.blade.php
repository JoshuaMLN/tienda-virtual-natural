@extends('layouts.account')

@section('title', 'Mi perfil | VitaNatural')
@section('accountActive', 'profile')

@section('accountContent')
<h1 class="section-title">Hola, Maria Fernanda</h1>
<p class="text-muted">Gestiona tu informacion personal y preferencias de cuenta.</p>

<div class="account-card p-4 mb-4">
    <div class="d-flex flex-wrap align-items-center gap-4">
        <img class="rounded-circle" src="https://images.unsplash.com/photo-1494790108377-be9c29b29330?auto=format&fit=crop&w=240&q=80" alt="Foto de perfil" width="112" height="112">
        <div class="flex-grow-1">
            <h2 class="h5 fw-black mb-1">Maria Fernanda Perez</h2>
            <p class="text-muted mb-1">maria.perez@email.com</p>
            <p class="small mb-0"><i class="bi bi-whatsapp text-vn-green"></i> 987 654 321</p>
            <span class="badge text-bg-warning mt-2">Cliente desde abr. 2024</span>
        </div>
        <button class="btn btn-green" type="button">Editar perfil</button>
    </div>
</div>

<h2 class="h5 fw-black mb-3">Resumen de tu actividad</h2>
<div class="row g-3 mb-4">
    @foreach([
        ['bi-bag-check', '12', 'Pedidos realizados'],
        ['bi-cash-coin', 'S/ 1,245.80', 'Total gastado'],
        ['bi-heart', '2', 'Direcciones guardadas'],
        ['bi-house-heart', '3', 'Lista de deseos'],
    ] as [$icon, $value, $label])
        <div class="col-6 col-lg-3">
            <div class="account-card p-3 text-center h-100">
                <i class="bi {{ $icon }} fs-2 text-vn-green"></i>
                <h3 class="h5 fw-black mt-2 mb-0">{{ $value }}</h3>
                <span class="small text-muted">{{ $label }}</span>
            </div>
        </div>
    @endforeach
</div>

<div class="account-card p-3">
    <h2 class="h5 fw-black">Ultimo pedido</h2>
    <div class="d-flex flex-wrap align-items-center gap-3">
        <div class="thumb-sm" style="background-image: url('https://images.unsplash.com/photo-1587854692152-cbe660dbde88?auto=format&fit=crop&w=300&q=80')"></div>
        <div class="flex-grow-1"><strong>VN-2024-000123</strong><br><span class="small text-muted">16 may. 2024</span></div>
        <strong>S/ 176.60</strong>
        <x-admin.status-badge status="Entregado" />
        <a class="btn btn-sm btn-vn-outline" href="{{ route('account.orders') }}">Ver detalles</a>
    </div>
</div>
@endsection
