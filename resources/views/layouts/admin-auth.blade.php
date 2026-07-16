<!doctype html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>@yield('title', 'Acceso administrativo | VitaNatural')</title>
    <link rel="preconnect" href="https://cdn.jsdelivr.net">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="{{ asset('vendor/bootstrap-icons/bootstrap-icons.min.css') }}" rel="stylesheet">
    <link href="{{ asset('css/app.css') }}" rel="stylesheet">
</head>
<body class="admin-auth-body">
    <main class="admin-auth-shell">
        <section class="admin-auth-panel" aria-labelledby="admin-auth-title">
            <a class="brand-mark mb-4" href="{{ route('shop.index') }}">
                <span class="brand-leaf"><i class="bi bi-flower1" aria-hidden="true"></i></span>
                <span>VitaNatural <span class="brand-subtitle">Administracion</span></span>
            </a>

            @if(session('status'))
                <div class="alert alert-success" role="status">{{ session('status') }}</div>
            @endif

            @yield('content')

            <div class="admin-auth-footer">
                <i class="bi bi-shield-lock" aria-hidden="true"></i>
                Acceso exclusivo para personal autorizado
            </div>
        </section>
    </main>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
    <script src="{{ asset('js/app.js') }}"></script>
</body>
</html>
