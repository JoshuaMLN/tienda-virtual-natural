@extends('layouts.shop')

@section('title', 'Crear cuenta | VitaNatural')

@section('content')
<section class="container py-5">
    <div class="checkout-card p-4 p-lg-5 mx-auto" style="max-width: 720px;">
        <h1 class="section-title text-center">Crea tu cuenta</h1>
        <p class="text-muted text-center">Guarda tus direcciones y conserva tu carrito entre sesiones.</p>

        @include('auth.partials.register-form', ['fieldPrefix' => 'register'])

        <p class="text-center small text-muted mb-0 mt-4">
            Ya tienes una cuenta?
            <a class="text-vn-green fw-bold" href="{{ route('login') }}">Inicia sesion</a>
        </p>
    </div>
</section>
@endsection
