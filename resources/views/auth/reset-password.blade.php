@extends('layouts.shop')

@section('title', 'Restablecer contrasena | VitaNatural')

@section('content')
@php($resetErrors = $errors->getBag('resetPassword'))

<section class="container py-5">
    <div class="checkout-card p-4 p-lg-5 mx-auto" style="max-width: 640px;">
        <h1 class="section-title text-center">Crea una nueva contrasena</h1>
        <p class="text-muted text-center">La nueva contrasena debe tener al menos 8 caracteres.</p>

        <form class="d-grid gap-3" method="POST" action="{{ route('password.update') }}" novalidate>
            @csrf
            <input name="token" type="hidden" value="{{ $token }}">

            <div>
                <label class="form-label" for="reset-email">Correo electronico</label>
                <input
                    class="form-control {{ $resetErrors->has('email') ? 'is-invalid' : '' }}"
                    id="reset-email"
                    name="email"
                    type="email"
                    value="{{ old('email', $email) }}"
                    autocomplete="email"
                    required
                    readonly
                >
                @if($resetErrors->has('email'))
                    <div class="invalid-feedback">{{ $resetErrors->first('email') }}</div>
                @endif
            </div>

            <div>
                <label class="form-label" for="reset-password">
                    Nueva contrasena <span class="text-danger" aria-hidden="true">*</span>
                </label>
                <div class="input-group">
                    <input
                        class="form-control {{ $resetErrors->has('password') ? 'is-invalid' : '' }}"
                        id="reset-password"
                        name="password"
                        type="password"
                        autocomplete="new-password"
                        minlength="8"
                        required
                    >
                    <button class="btn btn-outline-secondary" type="button" data-password-toggle="reset-password" aria-label="Mostrar contrasena" aria-pressed="false">
                        <i class="bi bi-eye" aria-hidden="true"></i>
                    </button>
                </div>
                @if($resetErrors->has('password'))
                    <div class="invalid-feedback d-block">{{ $resetErrors->first('password') }}</div>
                @endif
            </div>

            <div>
                <label class="form-label" for="reset-password-confirmation">
                    Repite tu contrasena <span class="text-danger" aria-hidden="true">*</span>
                </label>
                <div class="input-group">
                    <input
                        class="form-control"
                        id="reset-password-confirmation"
                        name="password_confirmation"
                        type="password"
                        autocomplete="new-password"
                        minlength="8"
                        required
                    >
                    <button class="btn btn-outline-secondary" type="button" data-password-toggle="reset-password-confirmation" aria-label="Mostrar contrasena" aria-pressed="false">
                        <i class="bi bi-eye" aria-hidden="true"></i>
                    </button>
                </div>
            </div>

            <button class="btn btn-green" type="submit">Restablecer contrasena</button>
        </form>
    </div>
</section>
@endsection
