@php($googleLabel = $googleLabel ?? 'Continuar con Google')

<div class="auth-provider-divider" aria-hidden="true">
    <span>o continua con</span>
</div>

<a class="btn w-100 auth-provider-button" href="{{ route('auth.google.redirect') }}">
    <img
        class="auth-provider-logo"
        src="{{ asset('images/brands/google-g-neutral@4x.png') }}"
        width="40"
        height="40"
        alt=""
        aria-hidden="true"
    >
    {{ $googleLabel }}
</a>

<p class="small text-muted text-center mt-2 mb-0">
    Si aun no tienes una cuenta, crearemos una y aceptaras nuestros
    <a class="text-vn-green fw-bold" href="{{ route('shop.terms') }}">terminos y condiciones</a>.
</p>
