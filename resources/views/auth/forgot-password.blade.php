@extends('layouts.shop')

@section('title', 'Recuperar contrasena | VitaNatural')

@section('content')
@php($forgotErrors = $errors->getBag('forgotPassword'))

<section class="container py-5">
    <div class="checkout-card p-4 p-lg-5 mx-auto text-center" style="max-width: 640px;">
        <span class="category-icon mx-auto mb-4"><i class="bi bi-lock-fill"></i></span>
        <h1 class="section-title">Olvidaste tu contrasena?</h1>
        <p class="text-muted">Ingresa tu correo electronico y te enviaremos un enlace para restablecer tu contrasena.</p>

        @if(session('status'))
            <div class="alert alert-success text-start" role="status">{{ session('status') }}</div>
        @endif

        <form class="text-start mx-auto" style="max-width: 420px;" method="POST" action="{{ route('password.email') }}" novalidate>
            @csrf
            <label class="form-label" for="forgot-email">
                Correo electronico <span class="text-danger" aria-hidden="true">*</span>
            </label>
            <input
                class="form-control {{ $forgotErrors->has('email') ? 'is-invalid' : '' }}"
                id="forgot-email"
                name="email"
                type="email"
                value="{{ old('email') }}"
                autocomplete="email"
                required
                autofocus
            >
            @if($forgotErrors->has('email'))
                <div class="invalid-feedback">{{ $forgotErrors->first('email') }}</div>
            @endif

            <button class="btn btn-green w-100 mt-3" type="submit">Enviar enlace de recuperacion</button>
            <a class="btn btn-link text-vn-green w-100 mt-2" href="{{ route('login') }}">Volver al inicio de sesion</a>
        </form>
    </div>

    <div class="container mt-4" style="max-width: 640px;">
        <div class="promo-tile p-4 d-flex gap-3 align-items-center">
            <i class="bi bi-shield-lock fs-2 text-vn-green"></i>
            <div><strong>Consejo de seguridad</strong><br><span class="small text-muted">Nunca compartas tu contrasena con otras personas.</span></div>
        </div>
    </div>
</section>
@endsection
