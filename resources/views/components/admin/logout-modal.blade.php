<div class="modal fade" id="adminLogoutConfirmationModal" tabindex="-1" aria-labelledby="adminLogoutConfirmationModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <h2 class="modal-title h5 fw-black" id="adminLogoutConfirmationModalLabel">
                    <i class="bi bi-box-arrow-left text-danger me-2" aria-hidden="true"></i>Confirmar cierre de sesion
                </h2>
                <button class="btn-close" type="button" data-bs-dismiss="modal" aria-label="Cerrar"></button>
            </div>
            <div class="modal-body">
                <p class="mb-1">Estas a punto de salir del panel administrativo.</p>
                <p class="text-muted small mb-0">Las operaciones no guardadas se perderan.</p>
            </div>
            <div class="modal-footer">
                <button class="btn btn-outline-secondary" type="button" data-bs-dismiss="modal">Cancelar</button>
                <form method="POST" action="{{ route('admin.logout') }}">
                    @csrf
                    <button class="btn btn-danger" type="submit">
                        <i class="bi bi-box-arrow-left me-1" aria-hidden="true"></i>Cerrar sesion
                    </button>
                </form>
            </div>
        </div>
    </div>
</div>
