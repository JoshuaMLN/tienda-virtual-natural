@extends('layouts.account')

@section('title', 'Mis pedidos | VitaNatural')
@section('accountActive', 'orders')

@section('accountContent')
<div class="mb-4">
    <h1 class="section-title mb-1">Mis pedidos</h1>
    <p class="text-muted mb-0">Revisa el historial de tus compras.</p>
</div>

<div class="account-card account-empty-state p-5 text-center">
    <span class="account-empty-icon" aria-hidden="true">
        <i class="bi bi-bag"></i>
    </span>
    <h2 class="h5 fw-black mt-3">Aun no tienes pedidos</h2>
    <p class="text-muted mx-auto">Cuando realices una compra, podras consultar aqui su estado y detalle.</p>
    <a class="btn btn-green" href="{{ route('shop.catalog') }}">Explorar productos</a>
</div>
@endsection
