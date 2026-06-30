@extends('layouts.checkout')

@section('title', 'Pago exitoso | VitaNatural')

@section('content')
<section class="status-page d-flex align-items-center py-5">
    <div class="container">
        <div class="checkout-card p-4 p-lg-5 mx-auto text-center" style="max-width: 560px;">
            <span class="status-icon status-success mb-4"><i class="bi bi-check-lg"></i></span>
            <h1 class="section-title text-success">Pago exitoso</h1>
            <p>Tu pedido ha sido confirmado.</p>
            <div class="checkout-card p-3 my-4 bg-vn-soft">
                <span class="small text-muted">Numero de pedido</span>
                <h2 class="h4 fw-black">VN-2024-000123</h2>
                <span class="small text-muted">Fecha: 16 de mayo de 2024 - 11:24 a.m.</span>
            </div>
            <div class="alert alert-success text-start small">Hemos enviado los detalles del pedido a tu correo de compra.</div>
            <a class="btn btn-green w-100" href="{{ route('account.orders') }}">Ver mis pedidos</a>
            <a class="btn btn-link text-vn-green mt-2" href="{{ route('shop.index') }}">Seguir comprando</a>
        </div>
    </div>
</section>
@endsection
