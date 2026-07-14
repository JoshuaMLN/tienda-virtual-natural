@extends('layouts.account')

@section('title', 'Seguridad | VitaNatural')
@section('accountActive', 'security')

@section('accountContent')
@php($passwordErrors = $errors->getBag('updatePassword'))

<h1 class="section-title">Seguridad</h1>
<p class="text-muted">Administra la verificacion de tu correo y tu metodo de acceso.</p>

<section class="account-card p-4 mb-4" aria-labelledby="security-email-title">
    <div class="d-flex flex-wrap align-items-center justify-content-between gap-3">
        <div>
            <h2 class="h5 fw-black mb-1" id="security-email-title">Correo electronico</h2>
            <p class="text-muted mb-2">{{ $user->email }}</p>
            @if($user->hasVerifiedEmail())
                <span class="badge text-bg-success"><i class="bi bi-check-circle me-1"></i>Verificado</span>
            @else
                <span class="badge text-bg-warning"><i class="bi bi-exclamation-circle me-1"></i>Pendiente de verificacion</span>
            @endif
        </div>

        @unless($user->hasVerifiedEmail())
            <form method="POST" action="{{ route('verification.send') }}">
                @csrf
                <button class="btn btn-vn-outline" type="submit">
                    <i class="bi bi-send me-1"></i>Reenviar enlace
                </button>
            </form>
        @endunless
    </div>

    @if(session('status') === 'verification-link-sent')
        <div class="alert alert-success mt-3 mb-0" role="status">
            Enviamos un nuevo enlace de verificacion a tu correo.
        </div>
    @endif
</section>

<section class="account-card p-4" aria-labelledby="security-password-title">
    <h2 class="h5 fw-black mb-1" id="security-password-title">
        {{ $user->password === null ? 'Define una contrasena' : 'Cambia tu contrasena' }}
    </h2>
    <p class="text-muted">
        Usa al menos 8 caracteres y evita reutilizar contrasenas de otros servicios.
    </p>

    @if(session('status') === 'password-updated')
        <div class="alert alert-success" role="status">Contrasena actualizada correctamente.</div>
    @endif

    <form class="row g-3" method="POST" action="{{ route('account.password.update') }}" novalidate>
        @csrf
        @method('PATCH')

        @if($user->password !== null)
            <div class="col-12">
                <label class="form-label" for="current-password">
                    Contrasena actual <span class="text-danger" aria-hidden="true">*</span>
                </label>
                <div class="input-group">
                    <input
                        class="form-control {{ $passwordErrors->has('current_password') ? 'is-invalid' : '' }}"
                        id="current-password"
                        name="current_password"
                        type="password"
                        autocomplete="current-password"
                        required
                    >
                    <button class="btn btn-outline-secondary" type="button" data-password-toggle="current-password" aria-label="Mostrar contrasena" aria-pressed="false">
                        <i class="bi bi-eye" aria-hidden="true"></i>
                    </button>
                </div>
                @if($passwordErrors->has('current_password'))
                    <div class="invalid-feedback d-block">{{ $passwordErrors->first('current_password') }}</div>
                @endif
            </div>
        @endif

        <div class="col-md-6">
            <label class="form-label" for="account-password">
                Nueva contrasena <span class="text-danger" aria-hidden="true">*</span>
            </label>
            <div class="input-group">
                <input
                    class="form-control {{ $passwordErrors->has('password') ? 'is-invalid' : '' }}"
                    id="account-password"
                    name="password"
                    type="password"
                    autocomplete="new-password"
                    minlength="8"
                    required
                >
                <button class="btn btn-outline-secondary" type="button" data-password-toggle="account-password" aria-label="Mostrar contrasena" aria-pressed="false">
                    <i class="bi bi-eye" aria-hidden="true"></i>
                </button>
            </div>
            @if($passwordErrors->has('password'))
                <div class="invalid-feedback d-block">{{ $passwordErrors->first('password') }}</div>
            @endif
        </div>

        <div class="col-md-6">
            <label class="form-label" for="account-password-confirmation">
                Repite tu contrasena <span class="text-danger" aria-hidden="true">*</span>
            </label>
            <div class="input-group">
                <input
                    class="form-control"
                    id="account-password-confirmation"
                    name="password_confirmation"
                    type="password"
                    autocomplete="new-password"
                    minlength="8"
                    required
                >
                <button class="btn btn-outline-secondary" type="button" data-password-toggle="account-password-confirmation" aria-label="Mostrar contrasena" aria-pressed="false">
                    <i class="bi bi-eye" aria-hidden="true"></i>
                </button>
            </div>
        </div>

        <div class="col-12">
            <button class="btn btn-green" type="submit">Guardar contrasena</button>
        </div>
    </form>
</section>
@endsection
