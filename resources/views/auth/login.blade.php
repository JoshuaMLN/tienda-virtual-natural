@extends('layouts.shop')

@section('title', 'Login y registro | VitaNatural')

@section('content')
@php
    $loginErrors = $errors->getBag('login');
    $showLoginOldInput = $loginErrors->any();
    $googleErrors = $errors->getBag('google');
    $rememberDays = max(1, (int) config('auth.remember.days', 30));
@endphp

<section class="container py-5">
    <div class="checkout-card p-3 p-lg-5">
        <div class="row g-4">
            <div class="col-lg-6 pe-lg-5 border-lg-end">
                <h1 class="h3 fw-black">Inicia sesion</h1>
                <p class="text-muted">Accede a tu cuenta para continuar.</p>

                @if(session('status'))
                    <div class="alert alert-success" role="status">{{ session('status') }}</div>
                @endif

                @if($googleErrors->any())
                    <div class="alert alert-danger" role="alert">{{ $googleErrors->first('google') }}</div>
                @endif

                <form class="d-grid gap-3" method="POST" action="{{ route('login.store') }}" novalidate>
                    @csrf

                    <div>
                        <label class="form-label" for="login-email">
                            Correo electronico <span class="text-danger" aria-hidden="true">*</span>
                        </label>
                        <input
                            class="form-control {{ $loginErrors->has('email') ? 'is-invalid' : '' }}"
                            id="login-email"
                            name="email"
                            type="email"
                            value="{{ $showLoginOldInput ? old('email') : '' }}"
                            autocomplete="email"
                            required
                            autofocus
                        >
                        @if($loginErrors->has('email'))
                            <div class="invalid-feedback">{{ $loginErrors->first('email') }}</div>
                        @endif
                    </div>

                    <div>
                        <div class="d-flex justify-content-between gap-3">
                            <label class="form-label" for="login-password">
                                Contrasena <span class="text-danger" aria-hidden="true">*</span>
                            </label>
                            <a class="small text-vn-green fw-bold" href="{{ route('password.request') }}">Olvidaste tu contrasena?</a>
                        </div>
                        <div class="input-group">
                            <input
                                class="form-control {{ $loginErrors->has('password') ? 'is-invalid' : '' }}"
                                id="login-password"
                                name="password"
                                type="password"
                                autocomplete="current-password"
                                required
                            >
                            <button
                                class="btn btn-outline-secondary"
                                type="button"
                                data-password-toggle="login-password"
                                aria-label="Mostrar contrasena"
                                aria-pressed="false"
                            >
                                <i class="bi bi-eye" aria-hidden="true"></i>
                            </button>
                        </div>
                        @if($loginErrors->has('password'))
                            <div class="invalid-feedback d-block">{{ $loginErrors->first('password') }}</div>
                        @endif
                    </div>

                    <div class="form-check">
                        <input
                            class="form-check-input"
                            id="remember"
                            name="remember"
                            type="checkbox"
                            value="1"
                            {{ $showLoginOldInput && old('remember') ? 'checked' : '' }}
                        >
                        <label class="form-check-label" for="remember">
                            Recordarme durante {{ $rememberDays }} {{ $rememberDays === 1 ? 'dia' : 'dias' }} en este dispositivo
                        </label>
                    </div>

                    <button class="btn btn-green" type="submit">Iniciar sesion</button>
                </form>

                @include('auth.partials.google-access')
            </div>

            <div class="col-lg-6 ps-lg-5">
                <h2 class="h3 fw-black">Crea tu cuenta</h2>
                <p class="text-muted">Guarda tus direcciones y conserva tu carrito entre sesiones.</p>
                @include('auth.partials.register-form', ['fieldPrefix' => 'login-register'])
                @include('auth.partials.google-access', ['googleLabel' => 'Registrarme con Google'])
            </div>
        </div>
    </div>

    <div class="mt-4">
        <x-shop.trust-badges />
    </div>
</section>
@endsection
