@props(['activeStep', 'maxStep'])

@php
    $steps = [
        1 => ['label' => 'Contacto y entrega', 'icon' => 'bi-person-check'],
        2 => ['label' => 'Comprobante de pago', 'icon' => 'bi-receipt'],
        3 => ['label' => 'Pago', 'icon' => 'bi-credit-card'],
    ];
@endphp

<nav class="checkout-progress" aria-label="Progreso del checkout">
    <ol>
        @foreach($steps as $number => $step)
            @php
                $available = $number <= $maxStep;
                $current = $number === $activeStep;
                $completed = $number < $maxStep && ! $current;
            @endphp
            <li class="{{ $current ? 'is-current' : '' }} {{ $completed ? 'is-complete' : '' }} {{ $available ? 'is-available' : 'is-locked' }}">
                @if($available && ! $current)
                    <a href="{{ route('checkout.index', ['paso' => $number]) }}">
                @else
                    <span @if($current) aria-current="step" @endif>
                @endif

                    <span class="checkout-progress-marker">
                        <i class="bi {{ $completed ? 'bi-check-lg' : $step['icon'] }}" aria-hidden="true"></i>
                    </span>
                    <span class="checkout-progress-copy">
                        <small>Etapa {{ $number }}</small>
                        <strong>{{ $step['label'] }}</strong>
                    </span>

                @if($available && ! $current)
                    </a>
                @else
                    </span>
                @endif
            </li>
        @endforeach
    </ol>
</nav>
