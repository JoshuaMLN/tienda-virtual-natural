@props(['active' => 'dashboard'])

<aside class="admin-sidebar p-3">
    <div class="admin-sidebar-header d-flex align-items-start justify-content-between gap-3 mb-4">
        <a class="brand-mark text-white" href="{{ route('admin.dashboard') }}">
            <span class="brand-leaf bg-white text-vn-green"><i class="bi bi-flower1"></i></span>
            <span>VitaNatural <span class="brand-subtitle text-white-50">Admin</span></span>
        </a>
        <button class="admin-sidebar-close d-lg-none" type="button" data-admin-sidebar-close aria-label="Cerrar menu">
            <i class="bi bi-x-lg" aria-hidden="true"></i>
        </button>
    </div>
    <nav class="admin-sidebar-nav d-grid gap-1" aria-label="Navegacion administrativa">
        <a class="{{ $active === 'dashboard' ? 'active' : '' }}" href="{{ route('admin.dashboard') }}"><i class="bi bi-speedometer2"></i> Dashboard</a>
        <a class="{{ $active === 'products' ? 'active' : '' }}" href="{{ route('admin.products.index') }}"><i class="bi bi-box-seam"></i> Productos</a>
        <a class="{{ $active === 'categories' ? 'active' : '' }}" href="{{ route('admin.categories.index') }}"><i class="bi bi-tags"></i> Categorias</a>
        <a class="{{ $active === 'brands' ? 'active' : '' }}" href="{{ route('admin.brands.index') }}"><i class="bi bi-award"></i> Marcas</a>
        <a class="{{ $active === 'orders' ? 'active' : '' }}" href="{{ route('admin.orders.index') }}"><i class="bi bi-receipt"></i> Pedidos</a>
        <a class="{{ $active === 'payments' ? 'active' : '' }}" href="{{ route('admin.payments.index') }}"><i class="bi bi-credit-card"></i> Pagos</a>
        <a class="{{ $active === 'stock' ? 'active' : '' }}" href="{{ route('admin.stock.index') }}"><i class="bi bi-clipboard-data"></i> Stock</a>
        <a class="{{ $active === 'banners' ? 'active' : '' }}" href="{{ route('admin.banners.index') }}"><i class="bi bi-megaphone"></i> Promociones</a>
        <a href="#"><i class="bi bi-ticket-perforated"></i> Cupones</a>
        <a class="{{ $active === 'legal' ? 'active' : '' }}" href="{{ route('admin.legal.index') }}"><i class="bi bi-file-earmark-lock"></i> Legal</a>
        <a href="#"><i class="bi bi-graph-up-arrow"></i> Reportes</a>
        <a class="{{ $active === 'settings' ? 'active' : '' }}" href="{{ route('admin.settings.edit') }}"><i class="bi bi-gear"></i> Configuracion</a>
    </nav>
    <div class="admin-sidebar-footer">
        <button
            class="admin-sidebar-logout"
            type="button"
            data-bs-toggle="modal"
            data-bs-target="#adminLogoutConfirmationModal"
        >
            <i class="bi bi-box-arrow-left"></i> Cerrar sesion
        </button>
    </div>
</aside>
