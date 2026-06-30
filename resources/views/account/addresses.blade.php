@extends('layouts.account')

@section('title', 'Mis direcciones | VitaNatural')
@section('accountActive', 'addresses')

@section('accountContent')
@php
    $addresses = [
        ['name' => 'Casa', 'person' => 'Maria Fernanda Perez', 'phone' => '987 654 321', 'address' => 'Av. Caminos del Inca 1234, Dpto. 502, Santiago de Surco, Lima', 'default' => true],
        ['name' => 'Trabajo', 'person' => 'Maria Fernanda Perez', 'phone' => '987 654 321', 'address' => 'Jr. Las Begonias 456, Oficina 301, San Isidro, Lima', 'default' => false],
        ['name' => 'Familia', 'person' => 'Maria Fernanda Perez', 'phone' => '987 654 321', 'address' => 'Calle Los Alamos 789, Urb. Los Rosales, Ate, Lima', 'default' => false],
    ];
@endphp

<div class="d-flex justify-content-between align-items-end gap-3 mb-4">
    <div>
        <h1 class="section-title mb-1">Mis direcciones</h1>
        <p class="text-muted mb-0">Gestiona las direcciones donde deseas recibir tus pedidos.</p>
    </div>
    <button class="btn btn-green" type="button"><i class="bi bi-plus-lg me-1"></i>Agregar direccion</button>
</div>

<div class="row g-3">
    @foreach($addresses as $address)
        <div class="col-md-6 col-xl-4">
            <div class="account-card p-4 h-100">
                <div class="d-flex justify-content-between mb-3">
                    <strong>{{ $address['name'] }}</strong>
                    @if($address['default'])
                        <span class="badge text-bg-success">Predeterminada</span>
                    @endif
                </div>
                <p class="small mb-1"><i class="bi bi-person"></i> {{ $address['person'] }}</p>
                <p class="small mb-1"><i class="bi bi-whatsapp"></i> {{ $address['phone'] }}</p>
                <p class="small text-muted">{{ $address['address'] }}</p>
                <div class="d-flex gap-2">
                    <button class="btn btn-sm btn-vn-outline" type="button">Editar</button>
                    <button class="btn btn-sm btn-outline-danger" type="button">Eliminar</button>
                </div>
            </div>
        </div>
    @endforeach
</div>

<div class="promo-tile p-4 mt-4 d-flex gap-3 align-items-center">
    <i class="bi bi-shield-check fs-2 text-vn-green"></i>
    <div><strong>Tu informacion esta segura</strong><br><span class="small text-muted">Usamos tu direccion solo para la entrega de tus pedidos.</span></div>
</div>
@endsection
