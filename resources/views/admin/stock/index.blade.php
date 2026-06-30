@extends('layouts.admin')

@section('title', 'Stock | VitaNatural Admin')
@section('adminActive', 'stock')

@section('content')
@php
    $stock = [
        ['product' => 'Omega 3 Premium', 'sku' => 'VN-OMEGA-120', 'stock' => 45, 'min' => 10, 'status' => 'Optimo'],
        ['product' => 'Maca negra en polvo', 'sku' => 'VN-MACA-250', 'stock' => 8, 'min' => 15, 'status' => 'Bajo'],
        ['product' => 'Mix de frutos secos', 'sku' => 'VN-MIX-250', 'stock' => 7, 'min' => 15, 'status' => 'Bajo'],
        ['product' => 'Proteina vegana vainilla', 'sku' => 'VN-PROT-600', 'stock' => 6, 'min' => 20, 'status' => 'Bajo'],
        ['product' => 'Vitamina C 1000 mg', 'sku' => 'VN-VITC-60', 'stock' => 60, 'min' => 20, 'status' => 'Optimo'],
    ];
@endphp

<h1 class="h3 fw-black">Stock</h1>
<p class="text-muted">Controla el inventario de tus productos.</p>

<div class="row g-3 mb-4">
    <div class="col-md-3"><x-admin.stat-card icon="bi-boxes" label="Productos totales" value="312" /></div>
    <div class="col-md-3"><x-admin.stat-card icon="bi-clipboard-check" label="Stock total" value="8,572" /></div>
    <div class="col-md-3"><x-admin.stat-card icon="bi-exclamation-triangle" label="Bajo stock" value="18" /></div>
    <div class="col-md-3"><x-admin.stat-card icon="bi-x-octagon" label="Sin stock" value="2" /></div>
</div>

<div class="row g-4">
    <div class="col-xl-9">
        <div class="admin-card p-3">
            <div class="table-responsive">
                <table class="table mb-0">
                    <thead><tr><th>Producto</th><th>SKU</th><th>Stock</th><th>Stock min.</th><th>Estado</th></tr></thead>
                    <tbody>
                        @foreach($stock as $item)
                            <tr>
                                <td><strong>{{ $item['product'] }}</strong></td>
                                <td>{{ $item['sku'] }}</td>
                                <td>{{ $item['stock'] }}</td>
                                <td>{{ $item['min'] }}</td>
                                <td><x-admin.status-badge :status="$item['status']" /></td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>
    </div>
    <div class="col-xl-3">
        <div class="admin-card p-3">
            <h2 class="h5 fw-black">Alertas de stock</h2>
            <p class="small text-danger">2 productos sin stock</p>
            <p class="small text-warning">18 productos con bajo stock</p>
            <button class="btn btn-vn-outline w-100 mb-2" type="button">Ajuste de stock</button>
            <button class="btn btn-vn-outline w-100 mb-2" type="button">Importar inventario</button>
            <button class="btn btn-vn-outline w-100" type="button">Exportar inventario</button>
        </div>
    </div>
</div>
@endsection
