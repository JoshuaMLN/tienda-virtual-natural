@extends('layouts.admin-auth')

@section('title', 'Nueva contrasena | Admin VitaNatural')

@section('content')
@php($resetErrors = $errors->getBag('resetPassword'))

<div class="mb-4">
    <span class="admin-auth-icon"><i class="bi bi-shield-check" aria-hidden="true"></i></span>
    <h1 class="h4 fw-black mt-3 mb-2" id="admin-auth-title">Crea una nueva contrasena</h1>
    <p class="text-muted mb-0">Usa al menos 12 caracteres, mayusculas, minusculas y numeros.</p>
</div>

<form class="d-grid gap-3" method="POST" action="{{ route('admin.password.update') }}" novalidate>
    @csrf
    <input name="token" type="hidden" value="{{ $token }}">

    <div>
        <label class="form-label" for="admin-reset-email">Correo electronico</label>
        <input
            class="form-control {{ $resetErrors->has('email') ? 'is-invalid' : '' }}"
            id="admin-reset-email"
            name="email"
            type="email"
            value="{{ old('email', $email) }}"
            autocomplete="username"
            required
            readonly
        >
        @if($resetErrors->has('email'))
            <div class="invalid-feedback">{{ $resetErrors->first('email') }}</div>
        @endif
    </div>

    <div>
        <label class="form-label" for="admin-reset-password">
            Nueva contrasena <span class="required-mark" aria-hidden="true">*</span>
        </label>
        <div class="input-group">
            <input
                class="form-control {{ $resetErrors->has('password') ? 'is-invalid' : '' }}"
                id="admin-reset-password"
                name="password"
                type="password"
                autocomplete="new-password"
                minlength="12"
                required
            >
            <button class="btn btn-outline-secondary" type="button" data-password-toggle="admin-reset-password" aria-label="Mostrar contrasena" aria-pressed="false">
                <i class="bi bi-eye" aria-hidden="true"></i>
            </button>
        </div>
        @if($resetErrors->has('password'))
            <div class="invalid-feedback d-block">{{ $resetErrors->first('password') }}</div>
        @endif
    </div>

    <div>
        <label class="form-label" for="admin-reset-password-confirmation">
            Confirma la contrasena <span class="required-mark" aria-hidden="true">*</span>
        </label>
        <div class="input-group">
            <input
                class="form-control"
                id="admin-reset-password-confirmation"
                name="password_confirmation"
                type="password"
                autocomplete="new-password"
                minlength="12"
                required
            >
            <button class="btn btn-outline-secondary" type="button" data-password-toggle="admin-reset-password-confirmation" aria-label="Mostrar contrasena" aria-pressed="false">
                <i class="bi bi-eye" aria-hidden="true"></i>
            </button>
        </div>
    </div>

    <button class="btn btn-green" type="submit">Restablecer acceso</button>
</form>
@endsection
