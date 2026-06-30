@props(['status' => 'Activo'])

@php
    $classes = [
        'Activo' => 'text-bg-success',
        'Pagado' => 'text-bg-success',
        'Entregado' => 'text-bg-success',
        'Enviado' => 'text-bg-primary',
        'Pendiente' => 'text-bg-warning',
        'Pendiente de envio' => 'text-bg-warning',
        'Bajo' => 'text-bg-warning',
        'Fallido' => 'text-bg-danger',
        'Cancelado' => 'text-bg-danger',
        'Inactivo' => 'text-bg-secondary',
        'Optimo' => 'text-bg-success',
    ];
@endphp

<span class="badge {{ $classes[$status] ?? 'text-bg-secondary' }}">{{ $status }}</span>
