@extends('layouts.admin')

@section('title', 'Pagos | VitaNatural Admin')
@section('adminActive', 'payments')

@section('content')
<h1 class="h3 fw-black">Pagos</h1>
<p class="text-muted">Administra los pagos procesados por Culqi.</p>

<div class="row g-3 mb-4">
    <div class="col-md-6 col-xl-3"><x-admin.stat-card icon="bi-check-circle" label="Pagos exitosos" value="S/ 24,850.50" trend="164 pagos" /></div>
    <div class="col-md-6 col-xl-3"><x-admin.stat-card icon="bi-clock" label="Pendientes" value="S/ 3,290.00" trend="21 pagos" /></div>
    <div class="col-md-6 col-xl-3"><x-admin.stat-card icon="bi-x-circle" label="Fallidos" value="S/ 1,450.00" trend="9 pagos" /></div>
    <div class="col-md-6 col-xl-3"><x-admin.stat-card icon="bi-bank" label="Total procesado" value="S/ 29,320.50" trend="Ultimos 30 dias" /></div>
</div>

<div class="admin-card p-3">
    <div class="table-responsive">
        <table class="table mb-0">
            <thead><tr><th>Orden</th><th>Monto</th><th>Metodo</th><th>Referencia Culqi</th><th>Estado</th><th>Fecha</th></tr></thead>
            <tbody>
                @foreach(['Exitoso', 'Exitoso', 'Pendiente', 'Exitoso', 'Fallido', 'Exitoso'] as $status)
                    <tr>
                        <td>VN-2024-00012{{ $loop->iteration }}</td>
                        <td><strong>S/ {{ [176.60,79.90,98.40,62.90,129.00,73.80][$loop->index] }}</strong></td>
                        <td><span class="payment-logo">VISA</span></td>
                        <td>CULQI-{{ strtoupper(substr(md5($loop->index), 0, 10)) }}</td>
                        <td><x-admin.status-badge :status="$status === 'Exitoso' ? 'Pagado' : $status" /></td>
                        <td>16/05/2024 10:2{{ $loop->index }}</td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    </div>
</div>
@endsection
