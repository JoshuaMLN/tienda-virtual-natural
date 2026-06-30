@props(['icon' => 'bi-graph-up', 'label' => 'Indicador', 'value' => '0', 'trend' => null])

<div class="stat-card h-100">
    <div class="d-flex align-items-center gap-3">
        <i class="bi {{ $icon }}"></i>
        <div>
            <p class="text-muted small mb-1">{{ $label }}</p>
            <h4 class="fw-black mb-0">{{ $value }}</h4>
            @if($trend)
                <span class="small text-success">{{ $trend }}</span>
            @endif
        </div>
    </div>
</div>
