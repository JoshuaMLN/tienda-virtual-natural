@extends('layouts.shop')

@section('title', 'Crear cuenta | VitaNatural')

@section('content')
<section class="container py-5">
    <div class="checkout-card p-4 p-lg-5 mx-auto" style="max-width: 720px;">
        <h1 class="section-title text-center">Crea tu cuenta</h1>
        <p class="text-muted text-center">Guarda direcciones, revisa pedidos y recibe beneficios.</p>
        <div class="row g-3">
            <div class="col-md-6">
                <label class="form-label">Nombre completo</label>
                <input class="form-control" type="text" placeholder="Tu nombre">
            </div>
            <div class="col-md-6">
                <label class="form-label">Telefono</label>
                <input class="form-control" type="tel" placeholder="987 654 321">
            </div>
            <div class="col-12">
                <label class="form-label">Correo electronico</label>
                <input class="form-control" type="email" placeholder="tu@email.com">
            </div>
            <div class="col-md-6">
                <label class="form-label">Contrasena</label>
                <input class="form-control" type="password" placeholder="Minimo 8 caracteres">
            </div>
            <div class="col-md-6">
                <label class="form-label">Repite tu contrasena</label>
                <input class="form-control" type="password" placeholder="Minimo 8 caracteres">
            </div>
            <div class="col-12">
                <label class="form-check">
                    <input class="form-check-input" type="checkbox"> Acepto terminos, condiciones y politicas de privacidad.
                </label>
            </div>
            <div class="col-12">
                <button class="btn btn-green w-100" type="button">Crear cuenta</button>
            </div>
        </div>
    </div>
</section>
@endsection
