@extends('layouts.admin')

@section('title', 'Categorias | VitaNatural Admin')
@section('adminActive', 'categories')

@section('content')
<div class="d-flex flex-wrap justify-content-between align-items-end gap-3 mb-4">
    <div>
        <h1 class="h3 fw-black mb-1">Categorias</h1>
        <p class="text-muted mb-0">Organiza las categorias visibles en la tienda.</p>
    </div>
    <a class="btn btn-green" href="{{ route('admin.categories.create') }}"><i class="bi bi-plus-lg me-1"></i>Nueva categoria</a>
</div>

<div class="admin-card p-3">
    <div class="admin-list-summary mb-3">
        <div class="d-flex flex-wrap align-items-center justify-content-between gap-2">
            <div>
                <div class="admin-list-summary-count">
                    @if($categorySummary['filtered'] === $categorySummary['total'])
                        {{ number_format($categorySummary['filtered']) }} {{ $categorySummary['filtered'] === 1 ? 'categoria registrada' : 'categorias registradas' }}
                    @else
                        Mostrando {{ number_format($categorySummary['filtered']) }} de {{ number_format($categorySummary['total']) }} categorias
                    @endif
                </div>
                <div class="small text-muted">Resumen segun los filtros actuales.</div>
            </div>

            @if(count($categorySummary['visible_stats']) > 0)
                <div class="admin-summary-chips d-none d-md-flex">
                    @foreach($categorySummary['visible_stats'] as $stat)
                        <span class="admin-summary-chip admin-summary-chip-{{ $stat['tone'] }}" data-summary-stat="{{ $stat['key'] }}">
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
                    data-bs-target="#categorySummaryMobile"
                    aria-expanded="false"
                    aria-controls="categorySummaryMobile"
                >
                    Resumen <i class="bi bi-chevron-down ms-1" aria-hidden="true"></i>
                </button>
            @endif
        </div>

        @if(count($categorySummary['stats']) > 0)
            <div class="collapse d-md-none mt-3" id="categorySummaryMobile">
                <div class="admin-summary-mobile-list">
                    @foreach($categorySummary['stats'] as $stat)
                        <div class="admin-summary-mobile-row" data-summary-stat="{{ $stat['key'] }}">
                            <span><i class="bi {{ $stat['icon'] }} me-2" aria-hidden="true"></i>{{ $stat['label'] }}</span>
                            <strong>{{ number_format($stat['value']) }}</strong>
                        </div>
                    @endforeach
                </div>
            </div>
        @endif
    </div>

    <form class="row g-2 mb-3" method="GET" action="{{ route('admin.categories.index') }}">
        <div class="col-md">
            <div class="input-group">
                <span class="input-group-text bg-white"><i class="bi bi-search"></i></span>
                <input class="form-control" name="q" type="search" value="{{ request('q') }}" placeholder="Buscar categoria...">
            </div>
        </div>
        <div class="col-md-auto">
            <select class="form-select" name="estado">
                <option value="">Todos los estados</option>
                <option value="activo" @selected(request('estado') === 'activo')>Activas</option>
                <option value="inactivo" @selected(request('estado') === 'inactivo')>Inactivas</option>
            </select>
        </div>
        <div class="col-md-auto d-flex gap-2">
            <button class="btn btn-vn" type="submit"><i class="bi bi-funnel me-1"></i>Filtrar</button>
            <a class="btn btn-outline-secondary" href="{{ route('admin.categories.index') }}" aria-label="Limpiar filtros"><i class="bi bi-x-lg"></i></a>
        </div>
    </form>

    <div class="table-responsive">
        <table class="table align-middle mb-0">
            <thead>
                <tr>
                    <th>Categoria</th>
                    <th>Imagen</th>
                    <th>Slug</th>
                    <th>Productos</th>
                    <th>Orden</th>
                    <th>Destacada</th>
                    <th>Estado</th>
                    <th class="text-end">Acciones</th>
                </tr>
            </thead>
            <tbody>
                @forelse($categories as $category)
                    <tr>
                        <td>
                            <strong>{{ $category->name }}</strong>
                            @if($category->description)
                                <div class="small text-muted text-truncate" style="max-width: 280px;">{{ $category->description }}</div>
                            @endif
                        </td>
                        <td>
                            @if($category->image_source)
                                <span class="thumb-sm d-inline-block" style="background-image: url('{{ $category->image_source }}')"></span>
                            @else
                                <span class="category-icon" style="height: 44px; width: 44px; font-size: 1.15rem;"><i class="bi {{ $category->icon_class ?? 'bi-grid' }}"></i></span>
                            @endif
                        </td>
                        <td>{{ $category->slug }}</td>
                        <td>{{ $category->products_count }}</td>
                        <td>{{ $category->sort_order }}</td>
                        <td>
                            @if($category->is_featured)
                                <span class="badge text-bg-success">Si</span>
                            @else
                                <span class="badge text-bg-secondary">No</span>
                            @endif
                        </td>
                        <td><x-admin.status-badge :status="$category->is_active ? 'Activo' : 'Inactivo'" /></td>
                        <td>
                            <div class="d-flex justify-content-end gap-1">
                                <a
                                    class="btn btn-sm btn-light"
                                    href="{{ route('admin.categories.edit', $category) }}"
                                    aria-label="Editar"
                                    data-bs-toggle="tooltip"
                                    data-bs-placement="top"
                                    data-bs-title="Editar"
                                ><i class="bi bi-pencil"></i></a>
                                <form method="POST" action="{{ route('admin.categories.toggle-status', $category) }}">
                                    @csrf
                                    @method('PATCH')
                                    <button
                                        class="btn btn-sm btn-light"
                                        type="submit"
                                        aria-label="{{ $category->is_active ? 'Desactivar' : 'Activar' }}"
                                        data-bs-toggle="tooltip"
                                        data-bs-placement="top"
                                        data-bs-title="{{ $category->is_active ? 'Desactivar' : 'Activar' }}"
                                    >
                                        <i class="bi {{ $category->is_active ? 'bi-toggle-on' : 'bi-toggle-off' }}"></i>
                                    </button>
                                </form>
                                <form method="POST" action="{{ route('admin.categories.destroy', $category) }}" onsubmit="return confirm('Deseas eliminar esta categoria?');">
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
                        <td class="text-center text-muted py-4" colspan="8">No hay categorias para mostrar.</td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>

    <div class="mt-3">
        {{ $categories->links() }}
    </div>
</div>
@endsection
