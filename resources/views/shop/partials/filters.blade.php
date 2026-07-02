<div class="filter-card p-3">
    <h6 class="fw-black mb-3">Filtrar por</h6>

    <form action="{{ route('shop.catalog') }}" method="GET" class="d-grid gap-3">
        <div>
            <label class="form-label small fw-bold">Buscar</label>
            <input class="form-control form-control-sm" name="q" type="search" value="{{ request('q') }}" placeholder="Producto, marca o SKU">
        </div>

        <div class="border-bottom pb-3">
            <strong class="small d-block mb-2">Categoria</strong>
            @foreach($categories as $filterCategory)
                <label class="form-check small mb-2">
                    <input class="form-check-input" name="categoria[]" type="checkbox" value="{{ $filterCategory->slug }}" @checked(in_array($filterCategory->slug, $selectedCategorySlugs ?? [], true))>
                    {{ $filterCategory->name }} <span class="text-muted">({{ $filterCategory->products_count }})</span>
                </label>
            @endforeach
        </div>

        <div class="border-bottom pb-3">
            <strong class="small d-block mb-2">Marca</strong>
            @foreach($brands as $brand)
                <label class="form-check small mb-2">
                    <input class="form-check-input" name="marca[]" type="checkbox" value="{{ $brand->slug }}" @checked(in_array($brand->slug, $selectedBrandSlugs ?? [], true))>
                    {{ $brand->name }} <span class="text-muted">({{ $brand->products_count }})</span>
                </label>
            @endforeach
        </div>

        <div class="border-bottom pb-3">
            <strong class="small d-block mb-2">Rango de precio</strong>
            <div class="row g-2">
                <div class="col-6">
                    <input class="form-control form-control-sm" name="precio_min" type="number" min="0" step="1" value="{{ request('precio_min') }}" placeholder="Min.">
                </div>
                <div class="col-6">
                    <input class="form-control form-control-sm" name="precio_max" type="number" min="0" step="1" value="{{ request('precio_max') }}" placeholder="Max.">
                </div>
            </div>
        </div>

        <div class="border-bottom pb-3">
            <strong class="small d-block mb-2">Promociones</strong>
            <label class="form-check small mb-0">
                <input class="form-check-input" name="oferta" type="checkbox" value="1" @checked(request()->boolean('oferta'))>
                Ofertas
            </label>
        </div>

        <input type="hidden" name="orden" value="{{ request('orden', 'destacados') }}">

        <button class="btn btn-green btn-sm" type="submit">Aplicar filtros</button>
        <a class="btn btn-link btn-sm text-vn-green" href="{{ route('shop.catalog') }}">Limpiar filtros</a>
    </form>
</div>
