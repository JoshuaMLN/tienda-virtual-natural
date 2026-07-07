@extends('layouts.admin')

@section('title', 'Stock | VitaNatural Admin')
@section('adminActive', 'stock')

@section('content')
<div class="d-flex flex-wrap justify-content-between align-items-end gap-3 mb-4">
    <div>
        <h1 class="h3 fw-black mb-1">Stock</h1>
        <p class="text-muted mb-0">Controla el inventario real de tus productos.</p>
    </div>
</div>

<div class="row g-3 mb-4">
    <div class="col-md-3"><x-admin.stat-card icon="bi-boxes" label="Productos totales" :value="number_format($summary['products'])" /></div>
    <div class="col-md-3"><x-admin.stat-card icon="bi-clipboard-check" label="Stock total" :value="number_format($summary['stock_units'])" /></div>
    <div class="col-md-3"><x-admin.stat-card icon="bi-exclamation-triangle" label="Bajo stock" :value="number_format($summary['low_stock'])" /></div>
    <div class="col-md-3"><x-admin.stat-card icon="bi-x-octagon" label="Sin stock" :value="number_format($summary['out_of_stock'])" /></div>
</div>

<div class="admin-card p-3">
    <form class="row g-2 mb-3" method="GET" action="{{ route('admin.stock.index') }}">
        <div class="col-lg-3 col-md-6">
            <div class="input-group">
                <span class="input-group-text bg-white"><i class="bi bi-search"></i></span>
                <input class="form-control" name="q" type="search" value="{{ request('q') }}" placeholder="Buscar producto o SKU...">
            </div>
        </div>
        <div class="col-lg col-md-6">
            <select class="form-select" name="categoria">
                <option value="">Todas las categorias</option>
                @foreach($categories as $category)
                    <option value="{{ $category->id }}" @selected((string) request('categoria') === (string) $category->id)>
                        {{ $category->name }}{{ $category->is_active ? '' : ' (inactiva)' }}
                    </option>
                @endforeach
            </select>
        </div>
        <div class="col-lg col-md-6">
            <select class="form-select" name="marca">
                <option value="">Todas las marcas</option>
                @foreach($brands as $brand)
                    <option value="{{ $brand->id }}" @selected((string) request('marca') === (string) $brand->id)>
                        {{ $brand->name }}{{ $brand->is_active ? '' : ' (inactiva)' }}
                    </option>
                @endforeach
            </select>
        </div>
        <div class="col-lg-auto col-md-6">
            <select class="form-select" name="estado_stock">
                <option value="">Todos los estados</option>
                <option value="optimo" @selected(request('estado_stock') === 'optimo')>Optimo</option>
                <option value="bajo-stock" @selected(request('estado_stock') === 'bajo-stock')>Bajo stock</option>
                <option value="sin-stock" @selected(request('estado_stock') === 'sin-stock')>Sin stock</option>
            </select>
        </div>
        <div class="col-lg-auto col-md-6 d-flex gap-2">
            <button class="btn btn-vn" type="submit"><i class="bi bi-funnel me-1"></i>Filtrar</button>
            <a class="btn btn-outline-secondary" href="{{ route('admin.stock.index') }}" aria-label="Limpiar filtros"><i class="bi bi-x-lg"></i></a>
        </div>
    </form>

    <div class="table-responsive">
        <table class="table align-middle mb-0">
            <thead>
                <tr>
                    <th>Producto</th>
                    <th>Categoria</th>
                    <th>Marca</th>
                    <th>Stock actual</th>
                    <th>Stock min.</th>
                    <th>Estado</th>
                    <th>Movimientos</th>
                    <th class="text-end">Acciones</th>
                </tr>
            </thead>
            <tbody>
                @forelse($products as $product)
                    <tr>
                        <td>
                            <div class="d-flex align-items-center gap-2">
                                <span class="thumb-sm d-inline-block flex-shrink-0" style="background-image: url('{{ $product->main_image_url }}')"></span>
                                <div>
                                    <strong>{{ $product->name }}</strong>
                                    <div class="small text-muted">{{ $product->sku }}</div>
                                </div>
                            </div>
                        </td>
                        <td>{{ $product->category?->name ?? 'Sin categoria' }}</td>
                        <td>{{ $product->brand?->name ?? 'Sin marca' }}</td>
                        <td>
                            <strong>{{ number_format($product->stock) }}</strong>
                        </td>
                        <td>{{ number_format($product->low_stock_threshold) }}</td>
                        <td><x-admin.status-badge :status="$product->stock_status_label" /></td>
                        <td>{{ number_format($product->inventory_movements_count) }}</td>
                        <td>
                            <div class="d-flex justify-content-end gap-1">
                                <a
                                    class="btn btn-sm btn-light"
                                    href="{{ route('admin.stock.movements.index', $product) }}"
                                    aria-label="Ver historial de movimientos"
                                    data-bs-toggle="tooltip"
                                    data-bs-placement="top"
                                    data-bs-title="Ver historial"
                                >
                                    <i class="bi bi-clock-history"></i>
                                </a>
                                {{-- data-bs-toggle="modal" y "tooltip" no coexisten en el mismo elemento;
                                     el tooltip va en el span wrapper. --}}
                                <span
                                    data-bs-toggle="tooltip"
                                    data-bs-placement="top"
                                    data-bs-title="Editar stock minimo"
                                >
                                    <button
                                        class="btn btn-sm btn-light"
                                        type="button"
                                        data-bs-toggle="modal"
                                        data-bs-target="#stockThresholdModal-{{ $product->id }}"
                                        aria-label="Editar alerta de stock minimo"
                                    >
                                        <i class="bi bi-bell"></i>
                                    </button>
                                </span>
                                <span
                                    data-bs-toggle="tooltip"
                                    data-bs-placement="top"
                                    data-bs-title="Registrar movimiento"
                                >
                                    <button
                                        class="btn btn-sm btn-light"
                                        type="button"
                                        data-bs-toggle="modal"
                                        data-bs-target="#stockMovementModal-{{ $product->id }}"
                                        aria-label="Registrar movimiento"
                                    >
                                        <i class="bi bi-plus-circle"></i>
                                    </button>
                                </span>
                            </div>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td class="text-center text-muted py-4" colspan="8">No hay productos para mostrar.</td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>

    @foreach($products as $product)
        @php
            $thresholdBag = 'threshold_'.$product->id;
            $thresholdErrors = $errors->getBag($thresholdBag);
            $isCurrentThresholdForm = (string) old('threshold_product_id') === (string) $product->id;
            $thresholdHasErrors = $isCurrentThresholdForm && $thresholdErrors->any();
            $thresholdValue = $isCurrentThresholdForm ? old('low_stock_threshold') : $product->low_stock_threshold;
            $movementErrors = $errors->getBag('movement');
            $isCurrentMovementForm = (string) old('movement_product_id') === (string) $product->id;
            $movementHasErrors = $isCurrentMovementForm && $movementErrors->any();
            $movementType = $isCurrentMovementForm ? old('type', 'in') : 'in';
        @endphp
        <div class="modal fade" id="stockThresholdModal-{{ $product->id }}" tabindex="-1" aria-labelledby="stockThresholdModalLabel-{{ $product->id }}" aria-hidden="true">
            <div class="modal-dialog modal-dialog-centered">
                <form class="modal-content" method="POST" action="{{ route('admin.stock.threshold.update', $product) }}">
                    @csrf
                    @method('PATCH')
                    <input type="hidden" name="threshold_product_id" value="{{ $product->id }}">

                    <div class="modal-header">
                        <div>
                            <h2 class="modal-title h5 fw-black mb-1" id="stockThresholdModalLabel-{{ $product->id }}">Editar alerta de stock minimo</h2>
                            <p class="small text-muted mb-0">{{ $product->name }} - {{ $product->sku }}</p>
                        </div>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Cerrar"></button>
                    </div>

                    <div class="modal-body">
                        <div class="row g-3">
                            <div class="col-6">
                                <span class="small text-muted d-block">Stock actual</span>
                                <strong>{{ number_format($product->stock) }}</strong>
                            </div>
                            <div class="col-6">
                                <span class="small text-muted d-block">Estado actual</span>
                                <x-admin.status-badge :status="$product->stock_status_label" />
                            </div>
                            <div class="col-12">
                                <label class="form-label" for="low_stock_threshold_{{ $product->id }}">
                                    Stock minimo de alerta <span class="required-mark" aria-hidden="true">*</span><span class="visually-hidden"> obligatorio</span>
                                </label>
                                <input
                                    class="form-control @if($thresholdHasErrors) is-invalid @endif"
                                    id="low_stock_threshold_{{ $product->id }}"
                                    name="low_stock_threshold"
                                    type="number"
                                    min="0"
                                    value="{{ $thresholdValue }}"
                                    required
                                >
                                <div class="form-text">Con 0 no se marca bajo stock.</div>
                                @if($thresholdHasErrors)
                                    <div class="invalid-feedback">{{ $thresholdErrors->first('low_stock_threshold') }}</div>
                                @endif
                            </div>
                        </div>
                    </div>

                    <div class="modal-footer">
                        <button class="btn btn-outline-secondary" type="button" data-bs-dismiss="modal">Cancelar</button>
                        <button class="btn btn-vn" type="submit">Guardar alerta</button>
                    </div>
                </form>
            </div>
        </div>

        <div class="modal fade" id="stockMovementModal-{{ $product->id }}" tabindex="-1" aria-labelledby="stockMovementModalLabel-{{ $product->id }}" aria-hidden="true">
            <div class="modal-dialog modal-dialog-centered modal-lg">
                <form class="modal-content" method="POST" action="{{ route('admin.stock.movements.store', $product) }}" data-inventory-movement-form>
                    @csrf
                    <input type="hidden" name="movement_product_id" value="{{ $product->id }}">

                    <div class="modal-header">
                        <div>
                            <h2 class="modal-title h5 fw-black mb-1" id="stockMovementModalLabel-{{ $product->id }}">Registrar movimiento</h2>
                            <p class="small text-muted mb-0">{{ $product->name }} - {{ $product->sku }}</p>
                        </div>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Cerrar"></button>
                    </div>

                    <div class="modal-body">
                        <div class="row g-3">
                            <div class="col-md-4">
                                <span class="small text-muted d-block">Stock actual</span>
                                <strong>{{ number_format($product->stock) }}</strong>
                            </div>
                            <div class="col-md-4">
                                <span class="small text-muted d-block">Stock minimo</span>
                                <strong>{{ number_format($product->low_stock_threshold) }}</strong>
                            </div>
                            <div class="col-md-4">
                                <span class="small text-muted d-block">Estado actual</span>
                                <x-admin.status-badge :status="$product->stock_status_label" />
                            </div>

                            <div class="col-md-4">
                                <label class="form-label" for="movement_type_{{ $product->id }}">
                                    Tipo de movimiento <span class="required-mark" aria-hidden="true">*</span><span class="visually-hidden"> obligatorio</span>
                                </label>
                                <select
                                    class="form-select @if($movementHasErrors && $movementErrors->has('type')) is-invalid @endif"
                                    id="movement_type_{{ $product->id }}"
                                    name="type"
                                    data-movement-type
                                    required
                                >
                                    <option value="in" @selected($movementType === 'in')>Ingreso</option>
                                    <option value="out" @selected($movementType === 'out')>Salida</option>
                                    <option value="adjustment" @selected($movementType === 'adjustment')>Ajuste</option>
                                </select>
                                @if($movementHasErrors && $movementErrors->has('type'))
                                    <div class="invalid-feedback">{{ $movementErrors->first('type') }}</div>
                                @endif
                            </div>

                            <div class="col-md-4" data-movement-quantity-field>
                                <label class="form-label" for="movement_quantity_{{ $product->id }}">
                                    Cantidad <span class="required-mark" aria-hidden="true">*</span><span class="visually-hidden"> obligatorio</span>
                                </label>
                                <input
                                    class="form-control @if($movementHasErrors && $movementErrors->has('quantity')) is-invalid @endif"
                                    id="movement_quantity_{{ $product->id }}"
                                    name="quantity"
                                    type="number"
                                    min="1"
                                    value="{{ $isCurrentMovementForm ? old('quantity') : '' }}"
                                    data-movement-quantity
                                >
                                @if($movementHasErrors && $movementErrors->has('quantity'))
                                    <div class="invalid-feedback">{{ $movementErrors->first('quantity') }}</div>
                                @endif
                            </div>

                            <div class="col-md-4" data-movement-adjustment-field>
                                <label class="form-label" for="movement_new_stock_{{ $product->id }}">
                                    Stock final <span class="required-mark" aria-hidden="true">*</span><span class="visually-hidden"> obligatorio</span>
                                </label>
                                <input
                                    class="form-control @if($movementHasErrors && $movementErrors->has('new_stock')) is-invalid @endif"
                                    id="movement_new_stock_{{ $product->id }}"
                                    name="new_stock"
                                    type="number"
                                    min="0"
                                    value="{{ $isCurrentMovementForm ? old('new_stock') : $product->stock }}"
                                    data-movement-new-stock
                                >
                                @if($movementHasErrors && $movementErrors->has('new_stock'))
                                    <div class="invalid-feedback">{{ $movementErrors->first('new_stock') }}</div>
                                @endif
                            </div>

                            <div class="col-md-4">
                                <label class="form-label" for="movement_reference_{{ $product->id }}">Referencia</label>
                                <input
                                    class="form-control @if($movementHasErrors && $movementErrors->has('reference')) is-invalid @endif"
                                    id="movement_reference_{{ $product->id }}"
                                    name="reference"
                                    type="text"
                                    value="{{ $isCurrentMovementForm ? old('reference') : '' }}"
                                >
                                <div class="form-text">Manual y opcional: orden de compra, guia, factura o conteo.</div>
                                @if($movementHasErrors && $movementErrors->has('reference'))
                                    <div class="invalid-feedback">{{ $movementErrors->first('reference') }}</div>
                                @endif
                            </div>

                            <div class="col-12">
                                <label class="form-label" for="movement_reason_{{ $product->id }}">
                                    Motivo <span class="required-mark" aria-hidden="true">*</span><span class="visually-hidden"> obligatorio</span>
                                </label>
                                <input
                                    class="form-control @if($movementHasErrors && $movementErrors->has('reason')) is-invalid @endif"
                                    id="movement_reason_{{ $product->id }}"
                                    name="reason"
                                    type="text"
                                    value="{{ $isCurrentMovementForm ? old('reason') : '' }}"
                                    required
                                >
                                @if($movementHasErrors && $movementErrors->has('reason'))
                                    <div class="invalid-feedback">{{ $movementErrors->first('reason') }}</div>
                                @endif
                            </div>

                            <div class="col-12">
                                <label class="form-label" for="movement_notes_{{ $product->id }}">Notas</label>
                                <textarea
                                    class="form-control @if($movementHasErrors && $movementErrors->has('notes')) is-invalid @endif"
                                    id="movement_notes_{{ $product->id }}"
                                    name="notes"
                                    rows="3"
                                >{{ $isCurrentMovementForm ? old('notes') : '' }}</textarea>
                                @if($movementHasErrors && $movementErrors->has('notes'))
                                    <div class="invalid-feedback">{{ $movementErrors->first('notes') }}</div>
                                @endif
                            </div>
                        </div>
                    </div>

                    <div class="modal-footer">
                        <button class="btn btn-outline-secondary" type="button" data-bs-dismiss="modal">Cancelar</button>
                        <button class="btn btn-vn" type="submit">Registrar movimiento</button>
                    </div>
                </form>
            </div>
        </div>

        @if($thresholdHasErrors)
            <script>
                document.addEventListener('DOMContentLoaded', function () {
                    var modal = document.getElementById('stockThresholdModal-{{ $product->id }}');

                    if (modal && window.bootstrap) {
                        new window.bootstrap.Modal(modal).show();
                    }
                });
            </script>
        @endif

        @if($movementHasErrors)
            <script>
                document.addEventListener('DOMContentLoaded', function () {
                    var modal = document.getElementById('stockMovementModal-{{ $product->id }}');

                    if (modal && window.bootstrap) {
                        new window.bootstrap.Modal(modal).show();
                    }
                });
            </script>
        @endif
    @endforeach

    <div class="mt-3">
        {{ $products->links() }}
    </div>
</div>
@endsection
