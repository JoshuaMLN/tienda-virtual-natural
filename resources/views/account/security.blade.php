@extends('layouts.account')

@section('title', 'Seguridad | VitaNatural')
@section('accountActive', 'security')

@section('accountContent')
@php($passwordErrors = $errors->getBag('updatePassword'))
@php($googleErrors = $errors->getBag('google'))

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

<section class="account-card p-4 mb-4" aria-labelledby="security-google-title">
    <div class="d-flex flex-wrap align-items-start justify-content-between gap-3">
        <div class="flex-grow-1">
            <div class="d-flex align-items-center gap-2 mb-1">
                <i class="bi bi-google fs-5" aria-hidden="true"></i>
                <h2 class="h5 fw-black mb-0" id="security-google-title">Acceso con Google</h2>
            </div>

            @if($googleAccount)
                <p class="text-muted mb-2">Google esta vinculado a {{ $user->email }}.</p>
                <span class="badge text-bg-success">
                    <i class="bi bi-link-45deg me-1" aria-hidden="true"></i>Vinculado
                </span>
            @else
                <p class="text-muted mb-0">
                    Vincula el mismo correo para iniciar sesion sin escribir tu contrasena.
                </p>
            @endif
        </div>

        @if($googleAccount)
            <button
                class="btn btn-outline-danger"
                type="button"
                data-bs-toggle="modal"
                data-bs-target="#unlink-google-modal"
                {{ $user->password === null ? 'disabled' : '' }}
            >
                <i class="bi bi-link-45deg me-1" aria-hidden="true"></i>Desvincular
            </button>
        @else
            <form method="POST" action="{{ route('account.google.link') }}">
                @csrf
                <button class="btn btn-vn-outline" type="submit">
                    <i class="bi bi-google me-1" aria-hidden="true"></i>Vincular Google
                </button>
            </form>
        @endif
    </div>

    @if($googleAccount && $user->password === null)
        <div class="alert alert-warning mt-3 mb-0" role="status">
            <i class="bi bi-info-circle me-1" aria-hidden="true"></i>
            Google es tu unico metodo de acceso. Define una contrasena antes de desvincularlo.
        </div>
    @endif

    @if($googleErrors->any())
        <div class="alert alert-danger mt-3 mb-0" role="alert">{{ $googleErrors->first('google') }}</div>
    @endif

    @if(session('status') === 'google-linked')
        <div class="alert alert-success mt-3 mb-0" role="status">Google se vinculo correctamente.</div>
    @elseif(session('status') === 'google-unlinked')
        <div class="alert alert-success mt-3 mb-0" role="status">Google se desvinculo correctamente.</div>
    @elseif(session('status') === 'google-already-linked')
        <div class="alert alert-info mt-3 mb-0" role="status">Google ya esta vinculado a tu cuenta.</div>
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

@if($googleAccount && $user->password !== null)
    <div class="modal fade" id="unlink-google-modal" tabindex="-1" aria-labelledby="unlink-google-title" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-header">
                    <h2 class="modal-title fs-5 fw-black" id="unlink-google-title">Desvincular Google</h2>
                    <button class="btn-close" type="button" data-bs-dismiss="modal" aria-label="Cerrar"></button>
                </div>
                <div class="modal-body">
                    Volveras a iniciar sesion utilizando tu correo y contrasena de VitaNatural.
                </div>
                <div class="modal-footer">
                    <button class="btn btn-outline-secondary" type="button" data-bs-dismiss="modal">Cancelar</button>
                    <form method="POST" action="{{ route('account.google.unlink') }}">
                        @csrf
                        @method('DELETE')
                        <button class="btn btn-danger" type="submit">Desvincular</button>
                    </form>
                </div>
            </div>
        </div>
    </div>
@endif
@endsection
