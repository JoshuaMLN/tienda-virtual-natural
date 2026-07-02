<!doctype html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>@yield('title', 'Admin | VitaNatural')</title>
    <link rel="preconnect" href="https://cdn.jsdelivr.net">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="{{ asset('vendor/bootstrap-icons/bootstrap-icons.min.css') }}" rel="stylesheet">
    <link href="{{ asset('css/app.css') }}" rel="stylesheet">
</head>
<body class="admin-layout">
    @php($adminActive = trim($__env->yieldContent('adminActive', 'dashboard')))
    <x-admin.sidebar :active="$adminActive" />
    <button class="admin-sidebar-backdrop d-lg-none" type="button" data-admin-sidebar-close aria-label="Cerrar menu"></button>

    <div class="admin-main">
        <x-admin.topbar />
        <main class="container-fluid p-3 p-lg-4">
            @foreach(['success' => 'success', 'info' => 'primary', 'warning' => 'warning', 'error' => 'danger'] as $flashKey => $flashClass)
                @if(session($flashKey))
                    <div class="alert alert-{{ $flashClass }} alert-dismissible fade show" role="alert">
                        {{ session($flashKey) }}
                        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Cerrar"></button>
                    </div>
                @endif
            @endforeach

            @if($errors->any())
                <div class="alert alert-danger alert-dismissible fade show" role="alert">
                    Revisa los campos marcados antes de continuar.
                    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Cerrar"></button>
                </div>
            @endif

            @yield('content')
        </main>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
    <script src="{{ asset('js/app.js') }}"></script>
</body>
</html>
