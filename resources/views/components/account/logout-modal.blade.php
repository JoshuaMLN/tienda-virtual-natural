<div
    class="modal fade"
    id="logoutConfirmationModal"
    tabindex="-1"
    aria-labelledby="logoutConfirmationModalLabel"
    aria-hidden="true"
>
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <h2 class="modal-title h5 fw-black" id="logoutConfirmationModalLabel">
                    <i class="bi bi-box-arrow-right text-danger me-2" aria-hidden="true"></i>Confirmar cierre de sesion
                </h2>
                <button class="btn-close" type="button" data-bs-dismiss="modal" aria-label="Cerrar"></button>
            </div>
            <div class="modal-body">
                <p class="mb-1">Estas a punto de cerrar tu sesion actual.</p>
                <p class="text-muted small mb-0">
                    Tendras que iniciar sesion nuevamente para acceder a tu cuenta y continuar con acciones protegidas.
                </p>
            </div>
            <div class="modal-footer">
                <button class="btn btn-vn-outline" type="button" data-bs-dismiss="modal">Cancelar</button>
                <form method="POST" action="{{ route('logout') }}">
                    @csrf
                    <button class="btn btn-danger" type="submit">
                        <i class="bi bi-box-arrow-right me-1" aria-hidden="true"></i>Cerrar sesion
                    </button>
                </form>
            </div>
        </div>
    </div>
</div>
