@props(['message', 'warnings' => []])

@php
    $visibleWarnings = array_slice($warnings, 0, 3);
    $additionalWarnings = array_slice($warnings, 3);
@endphp

<div
    class="alert alert-warning checkout-quote-notice mb-0"
    role="alert"
    tabindex="-1"
    data-checkout-error
    data-checkout-quote-conflict
>
    <div class="checkout-quote-notice-heading">
        <span class="checkout-quote-notice-icon">
            <i class="bi bi-exclamation-triangle-fill" aria-hidden="true"></i>
        </span>
        <div>
            <strong class="d-block">Actualizamos tu compra</strong>
            <p class="mb-0">{{ $message }}</p>
        </div>
    </div>

    @if($visibleWarnings)
        <div class="checkout-quote-notice-details">
            <span class="small fw-bold d-block mb-2">Detalles de los cambios</span>
            <ul class="checkout-quote-change-list mb-0">
                @foreach($visibleWarnings as $warning)
                    <li>{{ $warning }}</li>
                @endforeach
            </ul>

            @if($additionalWarnings)
                <details class="checkout-quote-more mt-2">
                    <summary>
                        Ver {{ count($additionalWarnings) }} {{ count($additionalWarnings) === 1 ? 'cambio mas' : 'cambios mas' }}
                        <i class="bi bi-chevron-down" aria-hidden="true"></i>
                    </summary>
                    <ul class="checkout-quote-change-list mt-2 mb-0">
                        @foreach($additionalWarnings as $warning)
                            <li>{{ $warning }}</li>
                        @endforeach
                    </ul>
                </details>
            @endif
        </div>
    @endif
</div>
