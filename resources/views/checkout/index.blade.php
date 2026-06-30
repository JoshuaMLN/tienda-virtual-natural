@extends('layouts.checkout')

@section('title', 'Finalizar compra | VitaNatural')

@section('content')
@php
    $items = [
        ['name' => 'Omega 3 Premium', 'price' => 'S/ 79.90', 'qty' => 1, 'image' => 'https://images.unsplash.com/photo-1587854692152-cbe660dbde88?auto=format&fit=crop&w=300&q=80'],
        ['name' => 'Maca negra en polvo', 'price' => 'S/ 69.80', 'qty' => 2, 'image' => 'https://images.unsplash.com/photo-1587049352851-8d4e89133924?auto=format&fit=crop&w=300&q=80'],
        ['name' => 'Mix de frutos secos', 'price' => 'S/ 26.90', 'qty' => 1, 'image' => 'https://images.unsplash.com/photo-1599599810769-bcde5a160d32?auto=format&fit=crop&w=300&q=80'],
    ];
@endphp

<section class="container py-5">
    <nav class="small text-muted mb-3">Inicio &gt; Carrito &gt; Checkout</nav>
    <h1 class="section-title mb-4">Finalizar compra</h1>
    <div class="row g-4 align-items-start">
        <div class="col-lg-7 d-grid gap-4">
            <x-checkout.customer-form />
            <x-checkout.shipping-form />
            <x-checkout.payment-methods />
            <x-checkout.culqi-payment-box />
        </div>
        <div class="col-lg-5">
            <x-shop.order-summary :items="$items" button="Pagar con Culqi" />
            <p class="small text-muted mt-3 text-center"><i class="bi bi-lock"></i> Tus datos de pago estan protegidos. Culqi procesa la transaccion.</p>
        </div>
    </div>
</section>
@endsection
