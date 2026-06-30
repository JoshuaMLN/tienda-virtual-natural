<div class="filter-card p-3">
    <h6 class="fw-black mb-3">Filtrar por</h6>

    <div class="border-bottom pb-3 mb-3">
        <strong class="small d-block mb-2">Categoria</strong>
        @foreach(['Vitaminas', 'Minerales', 'Omega y aceites', 'Proteinas', 'Antioxidantes', 'Otros'] as $filter)
            <label class="form-check small mb-2">
                <input class="form-check-input" type="checkbox"> {{ $filter }}
            </label>
        @endforeach
        <a class="small text-vn-green fw-bold" href="#">Ver mas</a>
    </div>

    <div class="border-bottom pb-3 mb-3">
        <strong class="small d-block mb-2">Marca</strong>
        <input class="form-control form-control-sm mb-2" type="search" placeholder="Buscar marca...">
        @foreach(['Nutrex', 'Amazonia', 'Good Nature', 'Nativa Organics', 'Andean Naturals'] as $brand)
            <label class="form-check small mb-2">
                <input class="form-check-input" type="checkbox"> {{ $brand }}
            </label>
        @endforeach
    </div>

    <div class="border-bottom pb-3 mb-3">
        <strong class="small d-block mb-2">Rango de precio</strong>
        <input class="form-range" type="range" min="0" max="300" value="120">
        <div class="d-flex justify-content-between small text-muted"><span>S/ 0</span><span>S/ 300</span></div>
    </div>

    <div class="border-bottom pb-3 mb-3">
        <strong class="small d-block mb-2">Formato</strong>
        @foreach(['Capsulas', 'Polvo', 'Liquido', 'Tabletas'] as $format)
            <label class="form-check small mb-2">
                <input class="form-check-input" type="checkbox"> {{ $format }}
            </label>
        @endforeach
    </div>

    <div>
        <strong class="small d-block mb-2">Beneficios</strong>
        @foreach(['Energia', 'Inmunidad', 'Digestion', 'Deporte', 'Cuidado de peso'] as $benefit)
            <label class="form-check small mb-2">
                <input class="form-check-input" type="checkbox"> {{ $benefit }}
            </label>
        @endforeach
    </div>
</div>
