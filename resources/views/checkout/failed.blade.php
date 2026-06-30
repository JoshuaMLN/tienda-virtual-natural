@extends('layouts.checkout')

@section('title', 'Pago no realizado | VitaNatural')

@section('content')
<section class="status-page d-flex align-items-center py-5">
    <div class="container">
        <div class="checkout-card p-4 p-lg-5 mx-auto text-center" style="max-width: 560px;">
            <span class="status-icon status-failed mb-4"><i class="bi bi-x-lg"></i></span>
            <h1 class="section-title text-danger">Pago no realizado</h1>
            <p>No pudimos procesar tu pago.</p>
            <div class="checkout-card p-3 my-4 bg-vn-soft">
                <span class="small text-muted">Numero de pedido</span>
                <h2 class="h4 fw-black">VN-2024-000123</h2>
                <p class="small mb-0">El pago fue rechazado o cancelado. Por favor, intenta nuevamente.</p>
            </div>
            <ul class="text-start small">
                <li>Fondos insuficientes.</li>
                <li>Tarjeta rechazada por el banco.</li>
                <li>Error de conexion.</li>
            </ul>
            <a class="btn btn-vn w-100" href="{{ route('checkout.index') }}">Intentar nuevamente</a>
            <a class="btn btn-vn-outline w-100 mt-2" href="{{ route('shop.cart') }}">Volver al carrito</a>
        </div>
    </div>
</section>
@endsection
