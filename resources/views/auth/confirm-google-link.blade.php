@extends('layouts.shop')

@section('title', 'Vincular Google | VitaNatural')

@section('content')
@php($linkErrors = $errors->getBag('googleLink'))

<section class="container py-5">
    <div class="checkout-card p-4 p-lg-5 mx-auto" style="max-width: 560px;">
        <div class="text-center mb-4">
            <i class="bi bi-shield-lock auth-confirm-icon" aria-hidden="true"></i>
            <h1 class="h3 fw-black mt-3">Confirma que la cuenta es tuya</h1>
            <p class="text-muted mb-0">
                Ya existe una cuenta VitaNatural con <strong>{{ $email }}</strong>.
                Ingresa su contrasena para vincular Google sin duplicarla.
            </p>
        </div>

        @if($linkErrors->has('google'))
            <div class="alert alert-danger" role="alert">{{ $linkErrors->first('google') }}</div>
        @endif

        <form class="d-grid gap-3" method="POST" action="{{ route('auth.google.confirm.store') }}" novalidate>
            @csrf

            <div>
                <label class="form-label" for="google-link-password">
                    Contrasena actual <span class="text-danger" aria-hidden="true">*</span>
                </label>
                <div class="input-group">
                    <input
                        class="form-control {{ $linkErrors->has('password') ? 'is-invalid' : '' }}"
                        id="google-link-password"
                        name="password"
                        type="password"
                        autocomplete="current-password"
                        required
                        autofocus
                    >
                    <button class="btn btn-outline-secondary" type="button" data-password-toggle="google-link-password" aria-label="Mostrar contrasena" aria-pressed="false">
                        <i class="bi bi-eye" aria-hidden="true"></i>
                    </button>
                </div>
                @if($linkErrors->has('password'))
                    <div class="invalid-feedback d-block">{{ $linkErrors->first('password') }}</div>
                @endif
            </div>

            <button class="btn btn-green" type="submit">
                <i class="bi bi-link-45deg me-1" aria-hidden="true"></i>Vincular y continuar
            </button>
            <a class="btn btn-link text-vn-green" href="{{ route('login') }}">Cancelar</a>
        </form>
    </div>
</section>
@endsection
