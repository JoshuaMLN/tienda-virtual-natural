@php
    $compact = $compact ?? false;
    $detailQuery = $detailQuery ?? [];
@endphp

@if($compact)
    <div class="table-responsive">
        <table class="table align-middle mb-0">
            <thead>
                <tr>
                    <th scope="col">Pedido</th>
                    <th scope="col">Cliente</th>
                    <th scope="col">Total</th>
                    <th scope="col">Estado</th>
                    <th class="text-end" scope="col">Acciones</th>
                </tr>
            </thead>
            <tbody>
                @forelse($orders as $item)
                    @php($order = $item['order'])
                    <tr>
                        <td>
                            <strong class="d-block">{{ $order->code }}</strong>
                            <span class="small text-muted">{{ $item['formatted_date'] }}</span>
                        </td>
                        <td>
                            <span class="d-block">{{ $order->customer_name }}</span>
                            <span class="small text-muted">{{ $order->customer_email }}</span>
                        </td>
                        <td class="fw-bold text-nowrap">{{ $item['formatted_total'] }}</td>
                        <td><x-account.order-status :status="$item['commercial_status']" /></td>
                        <td class="text-end">
                            <a
                                class="btn btn-sm btn-light"
                                href="{{ route('admin.orders.show', $order->code) }}"
                                aria-label="Ver pedido {{ $order->code }}"
                                data-bs-toggle="tooltip"
                                data-bs-title="Ver pedido"
                            >
                                <i class="bi bi-eye" aria-hidden="true"></i>
                            </a>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td class="text-center text-muted py-4" colspan="5">Todavia no hay pedidos reales.</td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>
@else
    <div class="table-responsive d-none d-lg-block">
        <table class="table admin-order-table align-middle mb-0">
            <thead>
                <tr>
                    <th scope="col">Pedido</th>
                    <th scope="col">Cliente</th>
                    <th scope="col">Contenido</th>
                    <th scope="col">Total</th>
                    <th scope="col">Pedido</th>
                    <th scope="col">Pago</th>
                    <th scope="col">Entrega</th>
                    <th class="text-end" scope="col">Acciones</th>
                </tr>
            </thead>
            <tbody>
                @foreach($orders as $item)
                    @php($order = $item['order'])
                    <tr>
                        <td>
                            <strong class="d-block">{{ $order->code }}</strong>
                            <span class="small text-muted d-block">{{ $item['formatted_date'] }} a las {{ $item['formatted_time'] }}</span>
                            <span class="admin-order-method">
                                <i class="bi {{ $order->delivery_method === \App\Enums\DeliveryMethod::Pickup ? 'bi-shop' : 'bi-truck' }}" aria-hidden="true"></i>
                                {{ $order->delivery_method->label() }}
                            </span>
                        </td>
                        <td>
                            <span class="fw-bold d-block">{{ $order->customer_name }}</span>
                            <span class="small text-muted d-block text-break">{{ $order->customer_email }}</span>
                        </td>
                        <td>
                            <span class="d-block">
                                {{ $item['product_count'] }}
                                {{ $item['product_count'] === 1 ? 'producto' : 'productos' }}
                            </span>
                            <span class="small text-muted">
                                {{ $item['total_quantity'] }}
                                {{ $item['total_quantity'] === 1 ? 'unidad' : 'unidades' }}
                            </span>
                        </td>
                        <td class="fw-black text-nowrap">{{ $item['formatted_total'] }}</td>
                        @foreach($item['technical_statuses'] as $status)
                            <td>
                                <x-admin.status-badge :status="$status['value']" />
                                @if($status['explanation'])
                                    <i
                                        class="bi bi-info-circle admin-order-status-help"
                                        role="img"
                                        aria-label="{{ $status['explanation'] }}"
                                        data-bs-toggle="tooltip"
                                        data-bs-title="{{ $status['explanation'] }}"
                                    ></i>
                                @endif
                            </td>
                        @endforeach
                        <td class="text-end">
                            <a
                                class="btn btn-sm btn-light"
                                href="{{ route('admin.orders.show', array_merge(['order' => $order->code], $detailQuery)) }}"
                                aria-label="Ver pedido {{ $order->code }}"
                                data-bs-toggle="tooltip"
                                data-bs-title="Ver detalle"
                            >
                                <i class="bi bi-eye" aria-hidden="true"></i>
                            </a>
                        </td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    </div>

    <div class="admin-order-mobile-list d-lg-none">
        @foreach($orders as $item)
            @php($order = $item['order'])
            <article class="admin-order-mobile-item">
                <div class="admin-order-mobile-heading">
                    <div>
                        <strong>{{ $order->code }}</strong>
                        <span>{{ $item['formatted_date'] }} a las {{ $item['formatted_time'] }}</span>
                    </div>
                    <x-account.order-status :status="$item['commercial_status']" />
                </div>

                <div class="admin-order-mobile-customer">
                    <strong>{{ $order->customer_name }}</strong>
                    <span>{{ $order->customer_email }}</span>
                </div>

                <dl class="admin-order-mobile-summary">
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
                    <div>
                        <dt>Contenido</dt>
                        <dd>{{ $item['total_quantity'] }} {{ $item['total_quantity'] === 1 ? 'unidad' : 'unidades' }}</dd>
                    </div>
                </dl>

                <div class="admin-order-mobile-statuses">
                    @foreach($item['technical_statuses'] as $status)
                        <div>
                            <span>{{ $status['label'] }}</span>
                            <x-admin.status-badge :status="$status['value']" />
                            @if($status['explanation'])
                                <small class="admin-order-status-explanation">{{ $status['explanation'] }}</small>
                            @endif
                        </div>
                    @endforeach
                </div>

                <a
                    class="btn btn-vn-outline w-100"
                    href="{{ route('admin.orders.show', array_merge(['order' => $order->code], $detailQuery)) }}"
                >
                    <i class="bi bi-eye" aria-hidden="true"></i>
                    Ver detalle
                </a>
            </article>
        @endforeach
    </div>
@endif
