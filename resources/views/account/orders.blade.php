@extends('layouts.account')

@section('title', 'Mis pedidos | VitaNatural')
@section('accountActive', 'orders')

@section('accountContent')
@php
    $orders = [
        ['id' => 'VN-2024-000123', 'date' => '18 may. 2024', 'total' => 'S/ 176.60', 'pay' => 'Pagado', 'ship' => 'Entregado'],
        ['id' => 'VN-2024-000112', 'date' => '03 may. 2024', 'total' => 'S/ 98.90', 'pay' => 'Pagado', 'ship' => 'Entregado'],
        ['id' => 'VN-2024-000098', 'date' => '22 abr. 2024', 'total' => 'S/ 210.00', 'pay' => 'Pagado', 'ship' => 'Enviado'],
        ['id' => 'VN-2024-000087', 'date' => '10 abr. 2024', 'total' => 'S/ 145.50', 'pay' => 'Pagado', 'ship' => 'Entregado'],
        ['id' => 'VN-2024-000076', 'date' => '28 mar. 2024', 'total' => 'S/ 87.00', 'pay' => 'Pagado', 'ship' => 'Entregado'],
    ];
@endphp

<div class="d-flex flex-wrap justify-content-between align-items-end gap-3 mb-4">
    <div>
        <h1 class="section-title mb-1">Mis pedidos</h1>
        <p class="text-muted mb-0">Revisa el historial de todos tus pedidos.</p>
    </div>
    <select class="form-select" style="max-width: 220px;">
        <option>Todos los estados</option>
        <option>Entregado</option>
        <option>Enviado</option>
        <option>Pendiente</option>
    </select>
</div>

<div class="account-card p-3">
    <div class="table-responsive">
        <table class="table mb-0">
            <thead>
                <tr><th>Pedido</th><th>Fecha</th><th>Total</th><th>Pago</th><th>Envio</th><th>Acciones</th></tr>
            </thead>
            <tbody>
                @foreach($orders as $order)
                    <tr>
                        <td><strong>{{ $order['id'] }}</strong></td>
                        <td>{{ $order['date'] }}</td>
                        <td><strong>{{ $order['total'] }}</strong></td>
                        <td><x-admin.status-badge :status="$order['pay']" /></td>
                        <td><x-admin.status-badge :status="$order['ship']" /></td>
                        <td><a class="btn btn-sm btn-link text-vn-green fw-bold" href="#">Ver detalle</a></td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    </div>
</div>
@endsection
