@extends('layouts.account')

@section('title', $order->code.' | Mis pedidos | VitaNatural')
@section('accountActive', 'orders')

@section('accountContent')
<div class="mb-4">
    <a class="account-back-link" href="{{ route('account.orders') }}">
        <i class="bi bi-arrow-left" aria-hidden="true"></i>
        Volver a mis pedidos
    </a>
    <div class="customer-order-detail-heading mt-3">
        <div>
            <p class="text-muted small mb-1">Pedido</p>
            <h1 class="section-title mb-0">{{ $order->code }}</h1>
        </div>
        <x-account.order-status :status="$commercialStatus" />
    </div>
</div>

<section class="account-card p-4" aria-labelledby="order-summary-title">
    <h2 class="h5 fw-black mb-3" id="order-summary-title">Resumen del pedido</h2>
    <dl class="customer-order-basic-summary mb-0">
        <div>
            <dt>Fecha</dt>
            <dd>{{ $order->created_at->format('d/m/Y') }}</dd>
        </div>
        <div>
            <dt>Modalidad</dt>
            <dd>{{ $order->delivery_method->label() }}</dd>
        </div>
        <div>
            <dt>Total</dt>
            <dd>{{ $formattedTotal }}</dd>
        </div>
    </dl>
</section>
@endsection
