@extends('layouts.account')

@section('title', 'Mis direcciones | VitaNatural')
@section('accountActive', 'addresses')

@section('accountContent')
<div class="mb-4">
    <h1 class="section-title mb-1">Mis direcciones</h1>
    <p class="text-muted mb-0">Consulta las direcciones asociadas a tu cuenta.</p>
</div>

<div class="account-card account-empty-state p-5 text-center">
    <span class="account-empty-icon" aria-hidden="true">
        <i class="bi bi-geo-alt"></i>
    </span>
    <h2 class="h5 fw-black mt-3">Aun no tienes direcciones guardadas</h2>
    <p class="text-muted mx-auto mb-0">Tus direcciones de entrega apareceran en este espacio.</p>
</div>
@endsection
