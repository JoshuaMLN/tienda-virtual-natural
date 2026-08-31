@extends('layouts.admin')

@section('title', 'Pedidos | VitaNatural Admin')
@section('adminActive', 'orders')

@section('content')
@php
    $hasFilters = collect($filters)
        ->filter(fn ($value) => $value !== null && $value !== '')
        ->isNotEmpty();
@endphp

<div class="d-flex flex-wrap justify-content-between align-items-end gap-3 mb-4">
    <div>
        <h1 class="h3 fw-black mb-1">Pedidos</h1>
        <p class="text-muted mb-0">Consulta y audita los pedidos reales de la tienda.</p>
    </div>
    <span class="admin-order-result-count">
        {{ number_format($orders->total()) }}
        {{ $orders->total() === 1 ? 'pedido' : 'pedidos' }}
    </span>
</div>

<section class="admin-card p-3 mb-4" aria-labelledby="admin-order-filters-title">
    <h2 class="visually-hidden" id="admin-order-filters-title">Filtros de pedidos</h2>
    <form method="GET" action="{{ route('admin.orders.index') }}">
        <div class="admin-order-filter-grid">
            <div class="admin-order-filter-search">
                <label class="form-label" for="admin-order-search">Buscar</label>
                <div class="input-group">
                    <span class="input-group-text bg-white" aria-hidden="true"><i class="bi bi-search"></i></span>
                    <input
                        class="form-control @error('q') is-invalid @enderror"
                        id="admin-order-search"
                        name="q"
                        type="search"
                        value="{{ $filters['q'] ?? '' }}"
                        placeholder="Codigo, cliente, correo o documento"
                        autocomplete="off"
                    >
                </div>
                @error('q')
                    <div class="invalid-feedback d-block">{{ $message }}</div>
                @enderror
            </div>

            <div>
                <label class="form-label" for="admin-order-status">Pedido</label>
                <select class="form-select @error('estado_pedido') is-invalid @enderror" id="admin-order-status" name="estado_pedido">
                    <option value="">Todos</option>
                    @foreach($orderStatuses as $status)
                        <option value="{{ $status->value }}" @selected(($filters['estado_pedido'] ?? null) === $status->value)>
                            {{ $status->label() }}
                        </option>
                    @endforeach
                </select>
                @error('estado_pedido')
                    <div class="invalid-feedback">{{ $message }}</div>
                @enderror
            </div>

            <div>
                <label class="form-label" for="admin-payment-status">Pago</label>
                <select class="form-select @error('estado_pago') is-invalid @enderror" id="admin-payment-status" name="estado_pago">
                    <option value="">Todos</option>
                    @foreach($paymentStatuses as $status)
                        <option value="{{ $status->value }}" @selected(($filters['estado_pago'] ?? null) === $status->value)>
                            {{ $status->label() }}
                        </option>
                    @endforeach
                </select>
                @error('estado_pago')
                    <div class="invalid-feedback">{{ $message }}</div>
                @enderror
            </div>

            <div>
                <label class="form-label" for="admin-delivery-status">Entrega</label>
                <select class="form-select @error('estado_entrega') is-invalid @enderror" id="admin-delivery-status" name="estado_entrega">
                    <option value="">Todos</option>
                    @foreach($deliveryStatuses as $status)
                        <option value="{{ $status->value }}" @selected(($filters['estado_entrega'] ?? null) === $status->value)>
                            {{ $status->label() }}
                        </option>
                    @endforeach
                </select>
                @error('estado_entrega')
                    <div class="invalid-feedback">{{ $message }}</div>
                @enderror
            </div>

            <div>
                <label class="form-label" for="admin-delivery-method">Modalidad</label>
                <select class="form-select @error('modalidad') is-invalid @enderror" id="admin-delivery-method" name="modalidad">
                    <option value="">Todas</option>
                    @foreach($deliveryMethods as $method)
                        <option value="{{ $method->value }}" @selected(($filters['modalidad'] ?? null) === $method->value)>
                            {{ $method->label() }}
                        </option>
                    @endforeach
                </select>
                @error('modalidad')
                    <div class="invalid-feedback">{{ $message }}</div>
                @enderror
            </div>

            <div>
                <label class="form-label" for="admin-fulfillment-status">Seguimiento</label>
                <select class="form-select @error('seguimiento') is-invalid @enderror" id="admin-fulfillment-status" name="seguimiento">
                    <option value="">Todos</option>
                    @foreach($fulfillmentFilters as $filter)
                        <option value="{{ $filter->value }}" @selected(($filters['seguimiento'] ?? null) === $filter->value)>
                            {{ $filter->label() }}
                        </option>
                    @endforeach
                </select>
                @error('seguimiento')
                    <div class="invalid-feedback">{{ $message }}</div>
                @enderror
            </div>

            <div>
                <label class="form-label" for="admin-order-from">Desde</label>
                <input
                    class="form-control @error('desde') is-invalid @enderror"
                    id="admin-order-from"
                    name="desde"
                    type="date"
                    value="{{ $filters['desde'] ?? '' }}"
                >
                @error('desde')
                    <div class="invalid-feedback">{{ $message }}</div>
                @enderror
            </div>

            <div>
                <label class="form-label" for="admin-order-until">Hasta</label>
                <input
                    class="form-control @error('hasta') is-invalid @enderror"
                    id="admin-order-until"
                    name="hasta"
                    type="date"
                    value="{{ $filters['hasta'] ?? '' }}"
                >
                @error('hasta')
                    <div class="invalid-feedback">{{ $message }}</div>
                @enderror
            </div>

            <div class="admin-order-filter-actions">
                <button class="btn btn-vn" type="submit">
                    <i class="bi bi-funnel" aria-hidden="true"></i>
                    Filtrar
                </button>
                @if($hasFilters)
                    <a class="btn btn-outline-secondary" href="{{ route('admin.orders.index') }}">
                        <i class="bi bi-x-lg" aria-hidden="true"></i>
                        Limpiar
                    </a>
                @endif
            </div>
        </div>
    </form>
</section>

@if($orders->isEmpty())
    <section class="admin-card admin-order-empty-state p-5 text-center">
        <span class="admin-order-empty-icon" aria-hidden="true">
            <i class="bi {{ $hasFilters ? 'bi-search' : 'bi-receipt' }}"></i>
        </span>
        @if($hasFilters)
            <h2 class="h5 fw-black mt-3">No encontramos pedidos</h2>
            <p class="text-muted mx-auto">Prueba con otros datos o limpia los filtros aplicados.</p>
            <a class="btn btn-vn" href="{{ route('admin.orders.index') }}">Ver todos los pedidos</a>
        @else
            <h2 class="h5 fw-black mt-3">Todavia no hay pedidos</h2>
            <p class="text-muted mx-auto mb-0">Los pedidos confirmados por los clientes apareceran aqui.</p>
        @endif
    </section>
@else
    <section class="admin-card admin-order-list-card">
        @include('admin.partials.orders-table', [
            'orders' => $orders,
            'compact' => false,
            'detailQuery' => $detailQuery,
        ])
    </section>

    <div class="mt-4">
        {{ $orders->links() }}
    </div>
@endif
@endsection
