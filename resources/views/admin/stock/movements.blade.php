@extends('layouts.admin')

@section('title', 'Historial de stock | VitaNatural Admin')
@section('adminActive', 'stock')

@section('content')
@php
    $movementErrors = $errors->getBag('movement');
    $movementHasErrors = $movementErrors->any();
    $movementType = old('type', 'in');
@endphp

<div class="d-flex flex-wrap justify-content-between align-items-end gap-3 mb-4">
    <div>
        <a class="small text-decoration-none text-muted" href="{{ route('admin.stock.index') }}">
            <i class="bi bi-arrow-left me-1"></i>Volver a stock
        </a>
        <h1 class="h3 fw-black mb-1 mt-2">Historial de stock</h1>
        <p class="text-muted mb-0">Movimientos registrados para {{ $product->name }}.</p>
    </div>
    <button class="btn btn-green" type="button" data-bs-toggle="modal" data-bs-target="#stockMovementModal">
        <i class="bi bi-plus-circle me-1"></i>Registrar movimiento
    </button>
</div>

<div class="row g-3 mb-4">
    <div class="col-lg-6">
        <div class="admin-card p-3 h-100">
            <div class="d-flex align-items-center gap-3">
                <span class="thumb-sm d-inline-block flex-shrink-0" style="background-image: url('{{ $product->main_image_url }}')"></span>
                <div>
                    <h2 class="h5 fw-black mb-1">{{ $product->name }}</h2>
                    <div class="small text-muted">{{ $product->sku }}</div>
                    <div class="small text-muted">
                        {{ $product->category?->name ?? 'Sin categoria' }} - {{ $product->brand?->name ?? 'Sin marca' }}
                    </div>
                </div>
            </div>
        </div>
    </div>
    <div class="col-lg-2 col-md-4"><x-admin.stat-card icon="bi-box-seam" label="Stock actual" :value="number_format($product->stock)" /></div>
    <div class="col-lg-2 col-md-4"><x-admin.stat-card icon="bi-bell" label="Stock min." :value="number_format($product->low_stock_threshold)" /></div>
    <div class="col-lg-2 col-md-4"><x-admin.stat-card icon="bi-clock-history" label="Movimientos" :value="number_format($movements->total())" /></div>
</div>

<div class="admin-card p-3">
    <div class="table-responsive">
        <table class="table align-middle mb-0">
            <thead>
                <tr>
                    <th>Fecha</th>
                    <th>Tipo</th>
                    <th>Cantidad</th>
                    <th>Stock anterior</th>
                    <th>Stock final</th>
                    <th>Motivo</th>
                    <th>Referencia</th>
                    <th>Usuario</th>
                </tr>
            </thead>
            <tbody>
                @forelse($movements as $movement)
                    @php
                        $type = [
                            'in' => ['label' => 'Ingreso', 'class' => 'text-bg-success'],
                            'out' => ['label' => 'Salida', 'class' => 'text-bg-danger'],
                            'adjustment' => ['label' => 'Ajuste', 'class' => 'text-bg-primary'],
                        ][$movement->type] ?? ['label' => $movement->type, 'class' => 'text-bg-secondary'];
                    @endphp
                    <tr>
                        <td>
                            <strong>{{ $movement->created_at?->format('d/m/Y') }}</strong>
                            <div class="small text-muted">{{ $movement->created_at?->format('H:i') }}</div>
                        </td>
                        <td><span class="badge {{ $type['class'] }}">{{ $type['label'] }}</span></td>
                        <td>{{ number_format($movement->quantity) }}</td>
                        <td>{{ number_format($movement->stock_before) }}</td>
                        <td>{{ number_format($movement->stock_after) }}</td>
                        <td>
                            <strong>{{ $movement->reason }}</strong>
                            @if($movement->notes)
                                <div class="small text-muted">{{ $movement->notes }}</div>
                            @endif
                        </td>
                        <td>{{ $movement->reference ?? '-' }}</td>
                        <td>{{ $movement->createdBy?->name ?? '-' }}</td>
                    </tr>
                @empty
                    <tr>
                        <td class="text-center text-muted py-4" colspan="8">Este producto aun no tiene movimientos registrados.</td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>

    <div class="mt-3">
        {{ $movements->links() }}
    </div>
</div>

<div class="modal fade" id="stockMovementModal" tabindex="-1" aria-labelledby="stockMovementModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-lg">
        <form class="modal-content" method="POST" action="{{ route('admin.stock.movements.store', $product) }}" data-inventory-movement-form>
            @csrf
            <input type="hidden" name="movement_product_id" value="{{ $product->id }}">

            <div class="modal-header">
                <div>
                    <h2 class="modal-title h5 fw-black mb-1" id="stockMovementModalLabel">Registrar movimiento</h2>
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
                        <label class="form-label" for="movement_type">
                            Tipo de movimiento <span class="required-mark" aria-hidden="true">*</span><span class="visually-hidden"> obligatorio</span>
                        </label>
                        <select
                            class="form-select @if($movementErrors->has('type')) is-invalid @endif"
                            id="movement_type"
                            name="type"
                            data-movement-type
                            required
                        >
                            <option value="in" @selected($movementType === 'in')>Ingreso</option>
                            <option value="out" @selected($movementType === 'out')>Salida</option>
                            <option value="adjustment" @selected($movementType === 'adjustment')>Ajuste</option>
                        </select>
                        @if($movementErrors->has('type'))
                            <div class="invalid-feedback">{{ $movementErrors->first('type') }}</div>
                        @endif
                    </div>

                    <div class="col-md-4" data-movement-quantity-field>
                        <label class="form-label" for="movement_quantity">
                            Cantidad <span class="required-mark" aria-hidden="true">*</span><span class="visually-hidden"> obligatorio</span>
                        </label>
                        <input
                            class="form-control @if($movementErrors->has('quantity')) is-invalid @endif"
                            id="movement_quantity"
                            name="quantity"
                            type="number"
                            min="1"
                            value="{{ old('quantity') }}"
                            data-movement-quantity
                        >
                        @if($movementErrors->has('quantity'))
                            <div class="invalid-feedback">{{ $movementErrors->first('quantity') }}</div>
                        @endif
                    </div>

                    <div class="col-md-4" data-movement-adjustment-field>
                        <label class="form-label" for="movement_new_stock">
                            Stock final <span class="required-mark" aria-hidden="true">*</span><span class="visually-hidden"> obligatorio</span>
                        </label>
                        <input
                            class="form-control @if($movementErrors->has('new_stock')) is-invalid @endif"
                            id="movement_new_stock"
                            name="new_stock"
                            type="number"
                            min="0"
                            value="{{ old('new_stock', $product->stock) }}"
                            data-movement-new-stock
                        >
                        @if($movementErrors->has('new_stock'))
                            <div class="invalid-feedback">{{ $movementErrors->first('new_stock') }}</div>
                        @endif
                    </div>

                    <div class="col-md-4">
                        <label class="form-label" for="movement_reference">Referencia</label>
                        <input
                            class="form-control @if($movementErrors->has('reference')) is-invalid @endif"
                            id="movement_reference"
                            name="reference"
                            type="text"
                            value="{{ old('reference') }}"
                        >
                        <div class="form-text">Manual y opcional: orden de compra, guia, factura o conteo.</div>
                        @if($movementErrors->has('reference'))
                            <div class="invalid-feedback">{{ $movementErrors->first('reference') }}</div>
                        @endif
                    </div>

                    <div class="col-12">
                        <label class="form-label" for="movement_reason">
                            Motivo <span class="required-mark" aria-hidden="true">*</span><span class="visually-hidden"> obligatorio</span>
                        </label>
                        <input
                            class="form-control @if($movementErrors->has('reason')) is-invalid @endif"
                            id="movement_reason"
                            name="reason"
                            type="text"
                            value="{{ old('reason') }}"
                            required
                        >
                        @if($movementErrors->has('reason'))
                            <div class="invalid-feedback">{{ $movementErrors->first('reason') }}</div>
                        @endif
                    </div>

                    <div class="col-12">
                        <label class="form-label" for="movement_notes">Notas</label>
                        <textarea
                            class="form-control @if($movementErrors->has('notes')) is-invalid @endif"
                            id="movement_notes"
                            name="notes"
                            rows="3"
                        >{{ old('notes') }}</textarea>
                        @if($movementErrors->has('notes'))
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

@if($movementHasErrors)
    <script>
        document.addEventListener('DOMContentLoaded', function () {
            var modal = document.getElementById('stockMovementModal');

            if (modal && window.bootstrap) {
                new window.bootstrap.Modal(modal).show();
            }
        });
    </script>
@endif
@endsection
