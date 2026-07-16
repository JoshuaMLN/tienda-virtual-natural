<header class="admin-topbar d-flex align-items-center px-3 px-lg-4 gap-3">
    <button class="btn btn-outline-secondary d-lg-none" type="button" data-admin-sidebar aria-label="Abrir menu">
        <i class="bi bi-list"></i>
    </button>
    <form class="d-none d-md-block flex-grow-1" style="max-width: 360px;">
        <div class="input-group">
            <span class="input-group-text bg-white"><i class="bi bi-search"></i></span>
            <input class="form-control" type="search" placeholder="Buscar en el sistema...">
        </div>
    </form>
    <div class="ms-auto d-flex align-items-center gap-3">
        <div class="dropdown">
            <button class="btn btn-light position-relative" type="button" data-bs-toggle="dropdown" aria-expanded="false" aria-label="Notificaciones">
                <i class="bi bi-bell"></i>
                @if(($adminNotificationCount ?? 0) > 0)
                    <span class="cart-count">{{ $adminNotificationCount }}</span>
                @endif
            </button>
            <div class="dropdown-menu dropdown-menu-end shadow" style="min-width: 300px; padding: 0;">
                <div class="p-3 border-bottom bg-light">
                    <strong class="mb-0">Notificaciones</strong>
                </div>
                @if(isset($adminNotifications) && count($adminNotifications) > 0)
                    <div class="list-group list-group-flush">
                        @foreach($adminNotifications as $notification)
                            <a href="{{ $notification->url ?? '#' }}" class="list-group-item list-group-item-action d-flex align-items-center gap-3 py-3">
                                <div class="text-{{ $notification->type }} fs-5">
                                    <i class="bi {{ $notification->icon }}"></i>
                                </div>
                                <div>
                                    <h6 class="mb-1 fw-bold">{{ $notification->title }}</h6>
                                    <p class="mb-0 small text-muted">{{ $notification->message }}</p>
                                </div>
                            </a>
                        @endforeach
                    </div>
                @else
                    <div class="p-4 text-center text-muted">
                        <i class="bi bi-check2-circle fs-2 text-success mb-2 d-block"></i>
                        Todo al dia.
                    </div>
                @endif
            </div>
        </div>
        <div class="dropdown">
            <button class="admin-account-trigger" type="button" data-bs-toggle="dropdown" aria-expanded="false" aria-label="Abrir menu administrativo">
                @if(auth()->user()->avatar_url)
                    <img class="admin-account-avatar" src="{{ auth()->user()->avatar_url }}" alt="" width="38" height="38">
                @else
                    <span class="admin-account-avatar admin-account-initials" aria-hidden="true">{{ auth()->user()->initials }}</span>
                @endif
                <span class="admin-account-copy d-none d-sm-block">
                    <strong>{{ auth()->user()->name }}</strong>
                    <small>Administrador</small>
                </span>
                <i class="bi bi-chevron-down small" aria-hidden="true"></i>
            </button>
            <ul class="dropdown-menu dropdown-menu-end admin-account-menu">
                <li class="px-3 py-2 border-bottom">
                    <strong class="d-block text-truncate">{{ auth()->user()->name }}</strong>
                    <span class="d-block small text-muted text-truncate">{{ auth()->user()->email }}</span>
                </li>
                <li><a class="dropdown-item" href="{{ route('shop.index') }}"><i class="bi bi-shop me-2"></i>Ver tienda</a></li>
                <li><hr class="dropdown-divider"></li>
                <li>
                    <button class="dropdown-item text-danger" type="button" data-bs-toggle="modal" data-bs-target="#adminLogoutConfirmationModal">
                        <i class="bi bi-box-arrow-left me-2"></i>Cerrar sesion
                    </button>
                </li>
            </ul>
        </div>
    </div>
</header>
