@extends('layouts.shop')

@section('title', 'Recuperar contrasena | VitaNatural')

@section('content')
<section class="container py-5">
    <div class="checkout-card p-4 p-lg-5 mx-auto text-center" style="max-width: 640px;">
        <span class="category-icon mx-auto mb-4"><i class="bi bi-lock-fill"></i></span>
        <h1 class="section-title">Olvidaste tu contrasena?</h1>
        <p class="text-muted">Ingresa tu correo electronico y te enviaremos un enlace para restablecer tu contrasena.</p>
        <div class="text-start mx-auto" style="max-width: 420px;">
            <label class="form-label">Correo electronico</label>
            <input class="form-control mb-3" type="email" placeholder="tu@email.com">
            <button class="btn btn-green w-100" type="button">Enviar enlace de recuperacion</button>
            <a class="btn btn-link text-vn-green w-100 mt-2" href="{{ route('login') }}">Volver al inicio de sesion</a>
        </div>
    </div>
    <div class="container mt-4" style="max-width: 640px;">
        <div class="promo-tile p-4 d-flex gap-3 align-items-center">
            <i class="bi bi-shield-lock fs-2 text-vn-green"></i>
            <div><strong>Consejo de seguridad</strong><br><span class="small text-muted">No compartas tu contrasena con nadie y cambiala periodicamente.</span></div>
        </div>
    </div>
</section>
@endsection
