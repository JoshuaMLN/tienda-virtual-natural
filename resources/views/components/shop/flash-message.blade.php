@php
    $messages = [
        'Sesion cerrada correctamente.' => ['type' => 'success', 'icon' => 'bi-check-circle-fill'],
        'Correo electronico verificado correctamente.' => ['type' => 'success', 'icon' => 'bi-envelope-check-fill'],
    ];
    $flash = $messages[session('status')] ?? null;
@endphp

@if($flash)
    <div class="container pt-3">
        <div class="alert alert-{{ $flash['type'] }} alert-dismissible fade show global-flash-message mb-0" role="status">
            <i class="bi {{ $flash['icon'] }}" aria-hidden="true"></i>
            <span>{{ session('status') }}</span>
            <button class="btn-close" type="button" data-bs-dismiss="alert" aria-label="Cerrar"></button>
        </div>
    </div>
@endif
