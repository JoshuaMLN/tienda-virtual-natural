@extends('layouts.admin')

@section('title', 'Dashboard | VitaNatural Admin')
@section('adminActive', 'dashboard')

@section('content')
<h1 class="h3 fw-black">Dashboard</h1>
<p class="text-muted">Resumen general de tu tienda.</p>
<div class="row g-3 mb-4">
    <div class="col-md-6 col-xl-3"><x-admin.stat-card icon="bi-cash-coin" label="Venta del dia" value="S/ 16,160.00" trend="+18.4% vs ayer" /></div>
    <div class="col-md-6 col-xl-3"><x-admin.stat-card icon="bi-receipt" label="Pedidos del dia" value="48" trend="+20.6% vs ayer" /></div>
    <div class="col-md-6 col-xl-3"><x-admin.stat-card icon="bi-box-seam" label="Productos activos" value="312" trend="+5 nuevos" /></div>
    <div class="col-md-6 col-xl-3"><x-admin.stat-card icon="bi-people" label="Clientes" value="2,845" trend="+32 nuevos" /></div>
</div>

<div class="row g-4">
    <div class="col-xl-7">
        <div class="admin-card p-4 h-100">
            <h2 class="h5 fw-black">Ventas ultimos 7 dias</h2>
            @foreach([45, 80, 64, 42, 70, 66, 92] as $value)
                <div class="d-flex align-items-center gap-3 mb-3">
                    <span class="small text-muted" style="width: 48px;">{{ 11 + $loop->index }} May</span>
                    <div class="progress flex-grow-1" style="height: 12px;"><div class="progress-bar bg-success" style="width: {{ $value }}%"></div></div>
                    <strong class="small">S/ {{ $value * 240 }}</strong>
                </div>
            @endforeach
        </div>
    </div>
    <div class="col-xl-5">
        <div class="admin-card p-4 h-100">
            <h2 class="h5 fw-black">Pedidos por estado</h2>
            @foreach([['Pagado', 24], ['Enviado', 16], ['Entregado', 17], ['Cancelado', 2]] as [$label, $count])
                <div class="d-flex align-items-center justify-content-between border-bottom py-2">
                    <span>{{ $label }}</span><strong>{{ $count }}</strong>
                </div>
            @endforeach
            <div class="text-center mt-4">
                <div class="display-4 fw-black text-vn-green">68</div>
                <span class="text-muted small">Total pedidos</span>
            </div>
        </div>
    </div>
    <div class="col-xl-8">
        <div class="admin-card p-3">
            <h2 class="h5 fw-black">Ultimos pedidos</h2>
            @include('admin.partials.orders-table')
        </div>
    </div>
    <div class="col-xl-4">
        <div class="admin-card p-3">
            <h2 class="h5 fw-black">Productos con bajo stock</h2>
            @foreach(['Omega 3 Premium', 'Maca negra en polvo', 'Proteina vegana vainilla', 'Mix de frutos secos'] as $product)
                <div class="d-flex justify-content-between border-bottom py-2 small">
                    <span>{{ $product }}</span><strong class="text-warning">Stock bajo</strong>
                </div>
            @endforeach
            <a class="btn btn-sm btn-vn-outline w-100 mt-3" href="{{ route('admin.stock.index') }}">Ver stock</a>
        </div>
    </div>
</div>
@endsection
