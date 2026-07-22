@extends('layouts.account')

@section('title', 'Mis pedidos | VitaNatural')
@section('accountActive', 'orders')

@section('accountContent')
<div class="customer-orders-heading mb-4">
    <div>
        <h1 class="section-title mb-1">Mis pedidos</h1>
        <p class="text-muted mb-0">Consulta el estado y el historial de tus compras.</p>
    </div>
    @if($orders->total() > 0)
        <span class="customer-orders-count">
            {{ $orders->total() }} {{ $orders->total() === 1 ? 'pedido' : 'pedidos' }}
        </span>
    @endif
</div>

<form class="account-card customer-order-filters p-3 mb-4" method="GET" action="{{ route('account.orders') }}">
    <div class="customer-order-filter-grid">
        <div>
            <label class="form-label" for="order-search">Buscar por codigo</label>
            <div class="input-group">
                <span class="input-group-text" aria-hidden="true"><i class="bi bi-search"></i></span>
                <input
                    class="form-control @error('q') is-invalid @enderror"
                    id="order-search"
                    name="q"
                    type="search"
                    value="{{ $search }}"
                    placeholder="PED-2026-000001"
                    autocomplete="off"
                >
            </div>
            @error('q')
                <div class="invalid-feedback d-block">{{ $message }}</div>
            @enderror
        </div>

        <div>
            <label class="form-label" for="order-status-filter">Estado</label>
            <select class="form-select @error('estado') is-invalid @enderror" id="order-status-filter" name="estado">
                @foreach($filters as $filter)
                    <option value="{{ $filter->value }}" @selected($activeFilter === $filter)>{{ $filter->label() }}</option>
                @endforeach
            </select>
            @error('estado')
                <div class="invalid-feedback">{{ $message }}</div>
            @enderror
        </div>

        <div class="customer-order-filter-actions">
            <button class="btn btn-green" type="submit">
                <i class="bi bi-funnel" aria-hidden="true"></i>
                Aplicar
            </button>
            @if($search !== '' || $activeFilter !== \App\Enums\CustomerOrderFilter::All)
                <a class="btn btn-outline-secondary" href="{{ route('account.orders') }}">
                    <i class="bi bi-x-lg" aria-hidden="true"></i>
                    Limpiar
                </a>
            @endif
        </div>
    </div>
</form>

@if($orders->isEmpty())
    <div class="account-card account-empty-state p-5 text-center">
        <span class="account-empty-icon" aria-hidden="true">
            <i class="bi {{ $search !== '' || $activeFilter !== \App\Enums\CustomerOrderFilter::All ? 'bi-search' : 'bi-bag' }}"></i>
        </span>
        @if($search !== '' || $activeFilter !== \App\Enums\CustomerOrderFilter::All)
            <h2 class="h5 fw-black mt-3">No encontramos pedidos</h2>
            <p class="text-muted mx-auto">Prueba con otro codigo o cambia el filtro de estado.</p>
            <a class="btn btn-green" href="{{ route('account.orders') }}">Ver todos los pedidos</a>
        @else
            <h2 class="h5 fw-black mt-3">Aun no tienes pedidos</h2>
            <p class="text-muted mx-auto">Cuando realices una compra, podras consultar aqui su estado y detalle.</p>
            <a class="btn btn-green" href="{{ route('shop.catalog') }}">Explorar productos</a>
        @endif
    </div>
@else
    <div class="account-card customer-order-table-wrap d-none d-md-block">
        <div class="table-responsive">
            <table class="table customer-order-table align-middle mb-0">
                <thead>
                    <tr>
                        <th scope="col">Pedido</th>
                        <th scope="col">Fecha</th>
                        <th scope="col">Total</th>
                        <th scope="col">Modalidad</th>
                        <th scope="col">Estado</th>
                        <th class="text-end" scope="col">Acciones</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($orders as $item)
                        @php($order = $item['order'])
                        <tr id="pedido-{{ $order->code }}">
                            <td class="fw-black">{{ $order->code }}</td>
                            <td>
                                <span class="d-block">{{ $item['formatted_date'] }}</span>
                                <span class="customer-order-time">{{ $item['formatted_time'] }}</span>
                            </td>
                            <td class="fw-bold text-nowrap">{{ $item['formatted_total'] }}</td>
                            <td>
                                <span class="customer-order-method">
                                    <i class="bi {{ $order->delivery_method === \App\Enums\DeliveryMethod::Pickup ? 'bi-shop' : 'bi-truck' }}" aria-hidden="true"></i>
                                    {{ $order->delivery_method->label() }}
                                </span>
                            </td>
                            <td><x-account.order-status :status="$item['status']" /></td>
                            <td class="text-end">
                                <a class="btn btn-sm btn-vn-outline text-nowrap" href="{{ route('account.orders.show', $order->code) }}">
                                    <i class="bi bi-eye" aria-hidden="true"></i>
                                    Ver pedido
                                </a>
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    </div>

    <div class="customer-order-mobile-list d-md-none">
        @foreach($orders as $item)
            @php($order = $item['order'])
            <article class="account-card customer-order-mobile-card" id="pedido-mobile-{{ $order->code }}">
                <div class="customer-order-mobile-header">
                    <div>
                        <div class="customer-order-mobile-code">{{ $order->code }}</div>
                        <time class="small text-muted" datetime="{{ $order->created_at->toAtomString() }}">
                            {{ $item['formatted_date'] }} a las {{ $item['formatted_time'] }}
                        </time>
                    </div>
                    <x-account.order-status :status="$item['status']" />
                </div>
                <dl class="customer-order-mobile-details">
                    <div>
                        <dt>Total</dt>
                        <dd>{{ $item['formatted_total'] }}</dd>
                    </div>
                    <div>
                        <dt>Modalidad</dt>
                        <dd>
                            <i class="bi {{ $order->delivery_method === \App\Enums\DeliveryMethod::Pickup ? 'bi-shop' : 'bi-truck' }}" aria-hidden="true"></i>
                            {{ $order->delivery_method->label() }}
                        </dd>
                    </div>
                </dl>
                <a class="btn btn-vn-outline w-100" href="{{ route('account.orders.show', $order->code) }}">
                    <i class="bi bi-eye" aria-hidden="true"></i>
                    Ver pedido
                </a>
            </article>
        @endforeach
    </div>

    <div class="mt-4">
        {{ $orders->links() }}
    </div>
@endif
@endsection
