@extends('layouts.admin-auth')

@section('title', 'Iniciar sesion | Admin VitaNatural')

@section('content')
@php($loginErrors = $errors->getBag('adminLogin'))

<div class="mb-4">
    <span class="admin-auth-icon"><i class="bi bi-person-lock" aria-hidden="true"></i></span>
    <h1 class="h4 fw-black mt-3 mb-2" id="admin-auth-title">Iniciar sesion</h1>
    <p class="text-muted mb-0">Ingresa con tu cuenta administrativa.</p>
</div>

<form class="d-grid gap-3" method="POST" action="{{ route('admin.login.store') }}" novalidate>
    @csrf

    <div>
        <label class="form-label" for="admin-login-email">
            Correo electronico <span class="required-mark" aria-hidden="true">*</span>
        </label>
        <input
            class="form-control {{ $loginErrors->has('email') ? 'is-invalid' : '' }}"
            id="admin-login-email"
            name="email"
            type="email"
            value="{{ old('email') }}"
            autocomplete="username"
            required
            autofocus
        >
        @if($loginErrors->has('email'))
            <div class="invalid-feedback">{{ $loginErrors->first('email') }}</div>
        @endif
    </div>

    <div>
        <div class="d-flex align-items-center justify-content-between gap-3">
            <label class="form-label" for="admin-login-password">
                Contrasena <span class="required-mark" aria-hidden="true">*</span>
            </label>
            <a class="small text-vn-green fw-bold" href="{{ route('admin.password.request') }}">Olvidaste tu contrasena?</a>
        </div>
        <div class="input-group">
            <input
                class="form-control {{ $loginErrors->has('password') ? 'is-invalid' : '' }}"
                id="admin-login-password"
                name="password"
                type="password"
                autocomplete="current-password"
                required
            >
            <button class="btn btn-outline-secondary" type="button" data-password-toggle="admin-login-password" aria-label="Mostrar contrasena" aria-pressed="false">
                <i class="bi bi-eye" aria-hidden="true"></i>
            </button>
        </div>
        @if($loginErrors->has('password'))
            <div class="invalid-feedback d-block">{{ $loginErrors->first('password') }}</div>
        @endif
    </div>

    <button class="btn btn-green" type="submit">
        <i class="bi bi-box-arrow-in-right me-1" aria-hidden="true"></i>Ingresar al panel
    </button>
</form>
@endsection
