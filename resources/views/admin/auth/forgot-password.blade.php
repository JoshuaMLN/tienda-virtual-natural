@extends('layouts.admin-auth')

@section('title', 'Recuperar acceso | Admin VitaNatural')

@section('content')
@php($forgotErrors = $errors->getBag('forgotPassword'))

<div class="mb-4">
    <span class="admin-auth-icon"><i class="bi bi-key" aria-hidden="true"></i></span>
    <h1 class="h4 fw-black mt-3 mb-2" id="admin-auth-title">Recuperar acceso</h1>
    <p class="text-muted mb-0">Te enviaremos un enlace temporal al correo administrativo.</p>
</div>

<form class="d-grid gap-3" method="POST" action="{{ route('admin.password.email') }}" novalidate>
    @csrf

    <div>
        <label class="form-label" for="admin-forgot-email">
            Correo electronico <span class="required-mark" aria-hidden="true">*</span>
        </label>
        <input
            class="form-control {{ $forgotErrors->has('email') ? 'is-invalid' : '' }}"
            id="admin-forgot-email"
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
    </div>

    <button class="btn btn-green" type="submit">Enviar enlace de recuperacion</button>
    <a class="btn btn-vn-outline" href="{{ route('admin.login') }}">Volver al inicio de sesion</a>
</form>
@endsection
