@extends('layouts.admin')

@section('title', 'Detalle de pedido | VitaNatural Admin')
@section('adminActive', 'orders')

@section('content')
@php
    $items = [
        ['name' => 'Omega 3 Premium', 'price' => 'S/ 79.90', 'qty' => 1, 'subtotal' => 'S/ 79.90', 'image' => 'https://images.unsplash.com/photo-1587854692152-cbe660dbde88?auto=format&fit=crop&w=300&q=80'],
        ['name' => 'Maca negra en polvo', 'price' => 'S/ 34.90', 'qty' => 1, 'subtotal' => 'S/ 34.90', 'image' => 'https://images.unsplash.com/photo-1587049352851-8d4e89133924?auto=format&fit=crop&w=300&q=80'],
        ['name' => 'Mix de frutos secos', 'price' => 'S/ 26.90', 'qty' => 1, 'subtotal' => 'S/ 26.90', 'image' => 'https://images.unsplash.com/photo-1599599810769-bcde5a160d32?auto=format&fit=crop&w=300&q=80'],
    ];
@endphp

<a class="small text-vn-green fw-bold" href="{{ route('admin.orders.index') }}"><i class="bi bi-arrow-left"></i> Volver a pedidos</a>
<div class="d-flex flex-wrap align-items-center gap-2 mt-2 mb-4">
    <h1 class="h3 fw-black mb-0">VN-2024-000123</h1>
    <x-admin.status-badge status="Pagado" />
    <x-admin.status-badge status="Pendiente de envio" />
</div>

<div class="row g-4">
    <div class="col-xl-8">
        <div class="admin-card p-3 mb-4">
            <h2 class="h5 fw-black">Cliente</h2>
            <div class="row small">
                <div class="col-md-6"><strong>Maria Fernanda Rodriguez</strong><br>maria@email.com<br>999 123 456</div>
                <div class="col-md-6"><strong>Direccion de envio</strong><br>Av. Del Sol 1234, Dpto 502<br>Miraflores, Lima</div>
            </div>
        </div>
        <div class="admin-card p-3">
            <h2 class="h5 fw-black">Productos</h2>
            <table class="table mb-0">
                <thead><tr><th>Producto</th><th>Precio</th><th>Cant.</th><th>Subtotal</th></tr></thead>
                <tbody>
                    @foreach($items as $item)
                        <tr>
                            <td><div class="d-flex gap-2 align-items-center"><div class="thumb-sm" style="background-image: url('{{ $item['image'] }}')"></div><strong>{{ $item['name'] }}</strong></div></td>
                            <td>{{ $item['price'] }}</td>
                            <td>{{ $item['qty'] }}</td>
                            <td><strong>{{ $item['subtotal'] }}</strong></td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    </div>
    <div class="col-xl-4">
        <x-shop.order-summary :items="$items" total="S/ 116.60" button="Marcar como enviado" />
        <div class="admin-card p-3 mt-4">
            <h2 class="h5 fw-black">Informacion de pago</h2>
            <p class="small mb-1"><strong>Metodo:</strong> Tarjeta con Culqi</p>
            <p class="small mb-1"><strong>Referencia:</strong> CULQI-XY84-2J4</p>
            <p class="small mb-1"><strong>Estado:</strong> <x-admin.status-badge status="Pagado" /></p>
            <button class="btn btn-vn-outline w-100 mt-3" type="button"><i class="bi bi-printer me-1"></i>Imprimir comprobante</button>
            <button class="btn btn-outline-danger w-100 mt-2" type="button"><i class="bi bi-x-circle me-1"></i>Cancelar pedido</button>
        </div>
    </div>
</div>
@endsection
