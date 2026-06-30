@extends('layouts.checkout')

@section('title', 'Pago pendiente | VitaNatural')

@section('content')
<section class="status-page d-flex align-items-center py-5">
    <div class="container">
        <div class="checkout-card p-4 p-lg-5 mx-auto text-center" style="max-width: 560px;">
            <span class="status-icon status-pending mb-4"><i class="bi bi-clock"></i></span>
            <h1 class="section-title text-warning">Pago en verificacion</h1>
            <p>Tu pago esta siendo verificado.</p>
            <div class="checkout-card p-3 my-4 bg-vn-soft">
                <span class="small text-muted">Numero de pedido</span>
                <h2 class="h4 fw-black">VN-2024-000123</h2>
                <p class="small mb-0">Estamos verificando tu pago con Culqi a traves del webhook.</p>
            </div>
            <div class="alert alert-warning text-start small">Este proceso puede tardar unos minutos. No es necesario que realices otra compra.</div>
            <a class="btn btn-warning w-100 text-white fw-bold" href="{{ route('account.orders') }}">Consultar estado del pedido</a>
            <a class="btn btn-link text-vn-green mt-2" href="{{ route('account.orders') }}">Ir a mis pedidos</a>
        </div>
    </div>
</section>
@endsection
