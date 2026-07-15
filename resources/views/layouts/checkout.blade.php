<!doctype html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>@yield('title', 'Checkout | VitaNatural')</title>
    <link rel="preconnect" href="https://cdn.jsdelivr.net">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="{{ asset('vendor/bootstrap-icons/bootstrap-icons.min.css') }}" rel="stylesheet">
    <link href="{{ asset('css/app.css') }}" rel="stylesheet">
</head>
<body class="bg-vn-soft">
    <div class="top-shipping-bar py-2 text-center">
        <i class="bi bi-truck"></i> Envio gratis a todo el Peru por compras desde S/ 149
    </div>

    <header class="bg-white border-bottom">
        <div class="container py-3 d-flex align-items-center justify-content-between gap-3">
            <a class="brand-mark" href="{{ route('shop.index') }}">
                    <span class="brand-leaf"><i class="bi bi-flower1"></i></span>
                <span>VitaNatural <span class="brand-subtitle">Bienestar que se nota</span></span>
            </a>
            <div class="d-none d-md-flex align-items-center gap-3 small fw-bold text-muted">
                <span><i class="bi bi-shield-check text-vn-green"></i> Compra segura</span>
                <span><i class="bi bi-lock text-vn-green"></i> Pago seguro con Culqi</span>
                <span><i class="bi bi-whatsapp text-vn-green"></i> Soporte WhatsApp</span>
            </div>
        </div>
    </header>

    <x-shop.flash-message />

    <main>
        @yield('content')
    </main>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
    <script src="{{ asset('js/app.js') }}"></script>
</body>
</html>
