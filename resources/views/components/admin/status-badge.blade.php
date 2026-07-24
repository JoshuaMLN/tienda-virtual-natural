@props(['status' => 'Activo'])

@php
    $classes = [
        'Activo' => 'text-bg-success',
        'Activa' => 'text-bg-success',
        'Pagado' => 'text-bg-success',
        'Completado' => 'text-bg-success',
        'Entregado' => 'text-bg-success',
        'Recogido' => 'text-bg-success',
        'Consumida' => 'text-bg-success',
        'Correo verificado' => 'text-bg-success',
        'Enviado' => 'text-bg-primary',
        'En camino' => 'text-bg-primary',
        'En preparacion' => 'text-bg-primary',
        'Procesando' => 'text-bg-primary',
        'Listo para recoger' => 'text-bg-primary',
        'En cola' => 'text-bg-primary',
        'Enviando' => 'text-bg-primary',
        'Pendiente' => 'text-bg-warning',
        'Pendiente de pago' => 'text-bg-warning',
        'Pendiente de envio' => 'text-bg-warning',
        'Bajo' => 'text-bg-warning',
        'Bajo stock' => 'text-bg-warning',
        'Correo sin verificar' => 'text-bg-warning',
        'No realizado' => 'text-bg-secondary',
        'No aplica' => 'text-bg-secondary',
        'Estado mixto' => 'text-bg-warning',
        'Sin stock' => 'text-bg-danger',
        'Fallido' => 'text-bg-danger',
        'Cancelado' => 'text-bg-danger',
        'Vencido' => 'text-bg-danger',
        'Vencida' => 'text-bg-danger',
        'Anulado' => 'text-bg-danger',
        'Inactivo' => 'text-bg-secondary',
        'Liberada' => 'text-bg-secondary',
        'Expirada' => 'text-bg-secondary',
        'Reembolsado' => 'text-bg-secondary',
        'Optimo' => 'text-bg-success',
    ];
@endphp

<span class="badge {{ $classes[$status] ?? 'text-bg-secondary' }}">{{ $status }}</span>
