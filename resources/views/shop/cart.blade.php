@extends('layouts.shop')

@section('title', 'Carrito | VitaNatural')

@section('content')
@php
    $items = [
        ['name' => 'Omega 3 Premium', 'description' => '120 capsulas', 'price' => 'S/ 79.90', 'qty' => 1, 'subtotal' => 'S/ 79.90', 'image' => 'https://images.unsplash.com/photo-1587854692152-cbe660dbde88?auto=format&fit=crop&w=300&q=80'],
        ['name' => 'Maca negra en polvo', 'description' => '200 g', 'price' => 'S/ 34.90', 'qty' => 2, 'subtotal' => 'S/ 69.80', 'image' => 'https://images.unsplash.com/photo-1587049352851-8d4e89133924?auto=format&fit=crop&w=300&q=80'],
        ['name' => 'Mix de frutos secos', 'description' => '250 g', 'price' => 'S/ 26.90', 'qty' => 1, 'subtotal' => 'S/ 26.90', 'image' => 'https://images.unsplash.com/photo-1599599810769-bcde5a160d32?auto=format&fit=crop&w=300&q=80'],
    ];
@endphp

<section class="container py-5">
    <h1 class="section-title mb-4">Tu carrito de compras</h1>
    <div class="row g-4">
        <div class="col-lg-8">
            <div class="checkout-card p-3">
                <div class="table-responsive">
                    <table class="table align-middle mb-0">
                        <thead>
                            <tr>
                                <th>Producto</th>
                                <th>Precio</th>
                                <th>Cantidad</th>
                                <th>Subtotal</th>
                                <th></th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach($items as $item)
                                <tr>
                                    <td>
                                        <div class="d-flex align-items-center gap-3">
                                            <div class="thumb-sm" style="background-image: url('{{ $item['image'] }}')"></div>
                                            <div><strong>{{ $item['name'] }}</strong><br><span class="small text-muted">{{ $item['description'] }}</span></div>
                                        </div>
                                    </td>
                                    <td><strong>{{ $item['price'] }}</strong></td>
                                    <td>
                                        <div class="quantity-control">
                                            <button data-quantity="minus" type="button">-</button>
                                            <input type="number" value="{{ $item['qty'] }}" min="1">
                                            <button data-quantity="plus" type="button">+</button>
                                        </div>
                                    </td>
                                    <td><strong>{{ $item['subtotal'] }}</strong></td>
                                    <td><button class="btn btn-link text-muted" type="button" aria-label="Eliminar"><i class="bi bi-trash"></i></button></td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </div>

            <div class="checkout-card p-3 mt-4">
                <label class="form-label fw-bold">Tienes un cupon de descuento?</label>
                <div class="input-group" style="max-width: 420px;">
                    <input class="form-control" type="text" placeholder="Ingresa tu cupon">
                    <button class="btn btn-vn-outline" type="button">Aplicar</button>
                </div>
            </div>
        </div>
        <div class="col-lg-4">
            <x-shop.order-summary :href="route('checkout.index')" :items="$items" />
        </div>
    </div>
    <div class="mt-4">
        <x-shop.trust-badges />
    </div>
</section>
@endsection
