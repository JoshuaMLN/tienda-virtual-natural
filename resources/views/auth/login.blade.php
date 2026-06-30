@extends('layouts.shop')

@section('title', 'Login y registro | VitaNatural')

@section('content')
<section class="container py-5">
    <div class="checkout-card p-3 p-lg-5">
        <div class="row g-4">
            <div class="col-lg-6 pe-lg-5 border-lg-end">
                <h1 class="h3 fw-black">Inicia sesion</h1>
                <p class="text-muted">Accede a tu cuenta para continuar.</p>
                <div class="d-grid gap-3">
                    <div>
                        <label class="form-label">Correo electronico</label>
                        <input class="form-control" type="email" placeholder="tu@email.com">
                    </div>
                    <div>
                        <div class="d-flex justify-content-between">
                            <label class="form-label">Contrasena</label>
                            <a class="small text-vn-green fw-bold" href="{{ route('password.request') }}">Olvidaste tu contrasena?</a>
                        </div>
                        <div class="input-group">
                            <input class="form-control" type="password" placeholder="********">
                            <button class="btn btn-outline-secondary" type="button" aria-label="Mostrar contrasena"><i class="bi bi-eye"></i></button>
                        </div>
                    </div>
                    <button class="btn btn-green" type="button">Iniciar sesion</button>
                    <div class="text-center text-muted small">o continua con</div>
                    <button class="btn btn-outline-secondary" type="button"><i class="bi bi-google me-2"></i>Continuar con Google</button>
                    <button class="btn btn-outline-secondary" type="button"><i class="bi bi-facebook me-2"></i>Continuar con Facebook</button>
                    <button class="btn btn-outline-secondary" type="button"><i class="bi bi-apple me-2"></i>Continuar con Apple</button>
                </div>
            </div>
            <div class="col-lg-6 ps-lg-5">
                <h2 class="h3 fw-black">Crea tu cuenta</h2>
                <p class="text-muted">Unete a VitaNatural y disfruta beneficios exclusivos.</p>
                <div class="d-grid gap-3">
                    <input class="form-control" type="text" placeholder="Nombre completo">
                    <input class="form-control" type="email" placeholder="Correo electronico">
                    <div class="input-group">
                        <input class="form-control" type="password" placeholder="Minimo 8 caracteres">
                        <button class="btn btn-outline-secondary" type="button" aria-label="Mostrar contrasena"><i class="bi bi-eye"></i></button>
                    </div>
                    <a class="btn btn-green" href="{{ route('register') }}">Crear mi cuenta</a>
                    <div class="text-center text-muted small">o continua con</div>
                    <button class="btn btn-outline-secondary" type="button"><i class="bi bi-google me-2"></i>Continuar con Google</button>
                    <button class="btn btn-outline-secondary" type="button"><i class="bi bi-facebook me-2"></i>Continuar con Facebook</button>
                    <button class="btn btn-outline-secondary" type="button"><i class="bi bi-apple me-2"></i>Continuar con Apple</button>
                </div>
            </div>
        </div>
    </div>
    <div class="mt-4">
        <x-shop.trust-badges />
    </div>
</section>
@endsection
