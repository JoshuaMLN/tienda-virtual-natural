@extends('layouts.shop')

@section('title', 'Verifica tu correo | VitaNatural')

@section('content')
<section class="container py-5">
    <div class="checkout-card p-4 p-lg-5 mx-auto text-center" style="max-width: 640px;">
        <span class="category-icon mx-auto mb-4"><i class="bi bi-envelope-check"></i></span>
        <h1 class="section-title">Verifica tu correo electronico</h1>
        <p class="text-muted">
            Enviamos un enlace de verificacion a <strong>{{ auth()->user()->email }}</strong>.
        </p>

        @if(session('status') === 'verification-link-sent')
            <div class="alert alert-success" role="status">
                Enviamos un nuevo enlace de verificacion a tu correo.
            </div>
        @endif

        <form method="POST" action="{{ route('verification.send') }}">
            @csrf
            <button class="btn btn-green w-100" type="submit">
                <i class="bi bi-send me-1"></i> Reenviar enlace
            </button>
        </form>

        <button
            class="btn btn-link logout-link-danger mt-2"
            type="button"
            data-bs-toggle="modal"
            data-bs-target="#logoutConfirmationModal"
        >
            <i class="bi bi-box-arrow-right me-1" aria-hidden="true"></i>Cerrar sesion
        </button>
    </div>
</section>

@endsection
