@extends('layouts.admin')

@section('title', 'Pedidos | VitaNatural Admin')
@section('adminActive', 'orders')

@section('content')
<div class="d-flex justify-content-between align-items-end mb-4">
    <div><h1 class="h3 fw-black mb-1">Pedidos</h1><p class="text-muted mb-0">Administra los pedidos de tu tienda.</p></div>
    <button class="btn btn-outline-secondary" type="button"><i class="bi bi-download me-1"></i>Exportar</button>
</div>

<div class="admin-card p-3">
    <div class="row g-2 mb-3">
        <div class="col-md"><select class="form-select"><option>Estado de pago: Todos</option></select></div>
        <div class="col-md"><select class="form-select"><option>Estado de envio: Todos</option></select></div>
        <div class="col-md"><input class="form-control" type="date"></div>
        <div class="col-md"><input class="form-control" type="search" placeholder="Buscar pedido o cliente..."></div>
    </div>
    @include('admin.partials.orders-table')
</div>
@endsection
