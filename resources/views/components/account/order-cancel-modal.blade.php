@props([
    'order',
    'returnTo' => 'detail',
])

@php($modalId = 'cancelOrderModal-'.$order->getKey())

<div class="modal fade" id="{{ $modalId }}" tabindex="-1" aria-labelledby="{{ $modalId }}Title" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <h2 class="modal-title h5" id="{{ $modalId }}Title">Cancelar pedido {{ $order->code }}</h2>
                <button class="btn-close" type="button" data-bs-dismiss="modal" aria-label="Cerrar"></button>
            </div>
            <div class="modal-body">
                <p class="mb-2">Liberaremos la reserva de stock y el pedido quedara cancelado.</p>
                <p class="small text-muted mb-0">Los productos no volveran automaticamente a tu carrito.</p>
            </div>
            <div class="modal-footer">
                <button class="btn btn-outline-secondary" type="button" data-bs-dismiss="modal">Conservar pedido</button>
                <form method="POST" action="{{ route('account.orders.cancel', $order->code) }}">
                    @csrf
                    @method('DELETE')
                    <input name="return_to" type="hidden" value="{{ $returnTo }}">
                    <button class="btn btn-danger" type="submit">
                        <i class="bi bi-x-circle" aria-hidden="true"></i>
                        Si, cancelar pedido
                    </button>
                </form>
            </div>
        </div>
    </div>
</div>
