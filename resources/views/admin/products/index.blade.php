@extends('layouts.admin')

@section('title', 'Productos | VitaNatural Admin')
@section('adminActive', 'products')

@section('content')
<div class="d-flex flex-wrap justify-content-between align-items-end gap-3 mb-4">
    <div>
        <h1 class="h3 fw-black mb-1">Productos</h1>
        <p class="text-muted mb-0">Administra el catalogo real de productos.</p>
    </div>
    <div class="d-flex flex-wrap gap-2">
        <a class="btn btn-outline-secondary" href="{{ route('admin.products.settings.edit') }}"><i class="bi bi-gear me-1"></i>Configuracion</a>
        <a class="btn btn-green" href="{{ route('admin.products.create') }}"><i class="bi bi-plus-lg me-1"></i>Nuevo producto</a>
    </div>
</div>

<div class="admin-card p-3">
    <div class="product-list-summary mb-3">
        <div class="d-flex flex-wrap align-items-center justify-content-between gap-2">
            <div>
                <div class="product-list-summary-count">
                    @if($productSummary['filtered'] === $productSummary['total'])
                        {{ number_format($productSummary['filtered']) }} {{ $productSummary['filtered'] === 1 ? 'producto en catalogo' : 'productos en catalogo' }}
                    @else
                        Mostrando {{ number_format($productSummary['filtered']) }} de {{ number_format($productSummary['total']) }} productos
                    @endif
                </div>
                <div class="small text-muted">Resumen segun los filtros actuales.</div>
            </div>

            @if(count($productSummary['visible_stats']) > 0)
                <div class="product-summary-chips d-none d-md-flex">
                    @foreach($productSummary['visible_stats'] as $stat)
                        <span class="product-summary-chip product-summary-chip-{{ $stat['tone'] }}" data-summary-stat="{{ $stat['key'] }}">
                            <i class="bi {{ $stat['icon'] }}" aria-hidden="true"></i>
                            <span>{{ $stat['label'] }}</span>
                            <strong>{{ number_format($stat['value']) }}</strong>
                        </span>
                    @endforeach
                </div>

                <button
                    class="btn btn-sm btn-outline-secondary d-md-none"
                    type="button"
                    data-bs-toggle="collapse"
                    data-bs-target="#productSummaryMobile"
                    aria-expanded="false"
                    aria-controls="productSummaryMobile"
                >
                    Resumen <i class="bi bi-chevron-down ms-1" aria-hidden="true"></i>
                </button>
            @endif
        </div>

        @if(count($productSummary['stats']) > 0)
            <div class="collapse d-md-none mt-3" id="productSummaryMobile">
                <div class="product-summary-mobile-list">
                    @foreach($productSummary['stats'] as $stat)
                        <div class="product-summary-mobile-row" data-summary-stat="{{ $stat['key'] }}">
                            <span><i class="bi {{ $stat['icon'] }} me-2" aria-hidden="true"></i>{{ $stat['label'] }}</span>
                            <strong>{{ number_format($stat['value']) }}</strong>
                        </div>
                    @endforeach
                </div>
            </div>
        @endif
    </div>

    <form class="row g-2 mb-3" method="GET" action="{{ route('admin.products.index') }}">
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
            <select class="form-select" name="estado">
                <option value="">Todos los estados</option>
                <option value="activo" @selected(request('estado') === 'activo')>Activos</option>
                <option value="inactivo" @selected(request('estado') === 'inactivo')>Inactivos</option>
            </select>
        </div>
        <div class="col-lg-auto col-md-6">
            <select class="form-select" name="publicacion">
                <option value="">Todas las publicaciones</option>
                <option value="publicado" @selected(request('publicacion') === 'publicado')>Publicado</option>
                <option value="oculto" @selected(request('publicacion') === 'oculto')>Oculto</option>
                <option value="programado" @selected(request('publicacion') === 'programado')>Programado</option>
                <option value="sin-publicar" @selected(request('publicacion') === 'sin-publicar')>Sin publicar</option>
            </select>
        </div>
        <div class="col-lg-auto col-md-6 d-flex gap-2">
            <button class="btn btn-vn" type="submit"><i class="bi bi-funnel me-1"></i>Filtrar</button>
            <a class="btn btn-outline-secondary" href="{{ route('admin.products.index') }}" aria-label="Limpiar filtros"><i class="bi bi-x-lg"></i></a>
        </div>
    </form>

    <div class="table-responsive">
        <table class="table align-middle mb-0">
            <thead>
                <tr>
                    <th>Imagen</th>
                    <th>Producto</th>
                    <th>Categoria</th>
                    <th>Marca</th>
                    <th>Precio</th>
                    <th>Stock</th>
                    <th>Estado</th>
                    <th>Publicacion</th>
                    <th class="text-end">Acciones</th>
                </tr>
            </thead>
            <tbody>
                @forelse($products as $product)
                    @php
                        $visibilityStatus = $product->visibility_status;
                        $canViewInStore = $visibilityStatus === 'publicado';
                        $viewInStoreTooltip = match ($visibilityStatus) {
                            'oculto' => $product->visibility_tooltip,
                            'programado' => 'Producto programado para publicarse el '.$product->published_at->format('d/m/Y H:i').'.',
                            'sin-publicar' => 'Agrega una fecha de publicacion para verlo en tienda.',
                            default => 'Ver en tienda',
                        };
                    @endphp
                    <tr>
                        <td><span class="thumb-sm d-inline-block" style="background-image: url('{{ $product->main_image_url }}')"></span></td>
                        <td>
                            <strong>{{ $product->name }}</strong>
                            <div class="small text-muted">{{ $product->sku }}</div>
                            @if($product->is_featured)
                                <span class="badge text-bg-warning mt-1">Destacado</span>
                            @endif
                        </td>
                        <td>{{ $product->category?->name ?? 'Sin categoria' }}</td>
                        <td>{{ $product->brand?->name ?? 'Sin marca' }}</td>
                        <td>
                            <strong>{{ $product->formatted_price }}</strong>
                            @if($product->formatted_compare_at_price)
                                <div class="small text-muted text-decoration-line-through">{{ $product->formatted_compare_at_price }}</div>
                            @endif
                        </td>
                        <td>
                            <div class="d-flex align-items-center gap-3">
                                <strong>{{ $product->stock }}</strong>
                                <x-admin.status-badge :status="$product->stock_status_label" />
                            </div>
                        </td>
                        <td><x-admin.status-badge :status="$product->is_active ? 'Activo' : 'Inactivo'" /></td>
                        <td>
                            @if($visibilityStatus === 'publicado')
                                <span class="badge text-bg-success">Publicado</span>
                                <div class="small text-muted">{{ $product->published_at->format('d/m/Y H:i') }}</div>
                            @elseif($visibilityStatus === 'oculto')
                                <span class="badge text-bg-warning">Oculto</span>
                                <i
                                    class="bi bi-question-circle text-warning ms-1"
                                    data-bs-toggle="tooltip"
                                    data-bs-placement="top"
                                    data-bs-title="{{ $product->visibility_tooltip }}"
                                    aria-label="{{ $product->visibility_tooltip }}"
                                ></i>
                                <div class="small text-muted">{{ $product->published_at->format('d/m/Y H:i') }}</div>
                            @elseif($visibilityStatus === 'programado')
                                <span class="badge text-bg-info text-white">Programado</span>
                                <div class="small text-muted">{{ $product->published_at->format('d/m/Y H:i') }}</div>
                            @else
                                <span class="badge text-bg-secondary">Sin publicar</span>
                            @endif
                        </td>

                        <td>
                            <div class="d-flex justify-content-end gap-1">
                                @if($canViewInStore)
                                    <a
                                        class="btn btn-sm btn-light"
                                        href="{{ route('shop.product', $product->slug) }}"
                                        target="_blank"
                                        aria-label="Ver en tienda"
                                        data-bs-toggle="tooltip"
                                        data-bs-placement="top"
                                        data-bs-title="Ver en tienda"
                                    ><i class="bi bi-eye"></i></a>
                                @else
                                    <span
                                        class="d-inline-block"
                                        tabindex="0"
                                        data-bs-toggle="tooltip"
                                        data-bs-placement="top"
                                        data-bs-title="{{ $viewInStoreTooltip }}"
                                    >
                                        <button
                                            class="btn btn-sm btn-light text-muted"
                                            type="button"
                                            disabled
                                            aria-label="{{ $viewInStoreTooltip }}"
                                        ><i class="bi bi-eye-slash"></i></button>
                                    </span>
                                @endif
                                <a
                                    class="btn btn-sm btn-light"
                                    href="{{ route('admin.products.edit', $product) }}"
                                    aria-label="Editar"
                                    data-bs-toggle="tooltip"
                                    data-bs-placement="top"
                                    data-bs-title="Editar"
                                ><i class="bi bi-pencil"></i></a>
                                <form method="POST" action="{{ route('admin.products.toggle-status', $product) }}">
                                    @csrf
                                    @method('PATCH')
                                    <button
                                        class="btn btn-sm btn-light"
                                        type="submit"
                                        aria-label="{{ $product->is_active ? 'Desactivar' : 'Activar' }}"
                                        data-bs-toggle="tooltip"
                                        data-bs-placement="top"
                                        data-bs-title="{{ $product->is_active ? 'Desactivar' : 'Activar' }}"
                                    >
                                        <i class="bi {{ $product->is_active ? 'bi-toggle-on' : 'bi-toggle-off' }}"></i>
                                    </button>
                                </form>
                                <form method="POST" action="{{ route('admin.products.toggle-publication', $product) }}">
                                    @csrf
                                    @method('PATCH')
                                    <button
                                        class="btn btn-sm btn-light"
                                        type="submit"
                                        aria-label="{{ $product->published_at ? 'Despublicar' : 'Publicar' }}"
                                        data-bs-toggle="tooltip"
                                        data-bs-placement="top"
                                        data-bs-title="{{ $product->published_at ? 'Despublicar' : 'Publicar' }}"
                                    >
                                        <i class="bi {{ $product->published_at ? 'bi-cloud-slash' : 'bi-cloud-upload' }}"></i>
                                    </button>
                                </form>
                                <form method="POST" action="{{ route('admin.products.destroy', $product) }}" onsubmit="return confirm('Deseas eliminar este producto?');">
                                    @csrf
                                    @method('DELETE')
                                    <button
                                        class="btn btn-sm btn-light text-danger"
                                        type="submit"
                                        aria-label="Eliminar"
                                        data-bs-toggle="tooltip"
                                        data-bs-placement="top"
                                        data-bs-title="Eliminar"
                                    ><i class="bi bi-trash"></i></button>
                                </form>
                            </div>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td class="text-center text-muted py-4" colspan="9">No hay productos para mostrar.</td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>

    <div class="mt-3">
        {{ $products->links() }}
    </div>
</div>
@endsection
