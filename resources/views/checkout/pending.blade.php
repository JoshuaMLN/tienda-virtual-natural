@extends('layouts.checkout')

@section('title', 'Pago pendiente | VitaNatural')

@section('content')
<section class="status-page d-flex align-items-center py-5">
    <div class="container">
        <div
            class="checkout-card pending-order-card p-4 p-lg-5 mx-auto text-center"
            data-pending-order-state
            data-status-url="{{ route('checkout.order.status', $order->code) }}"
            data-poll-interval="10000"
        >
            <span class="status-icon status-pending mb-4" data-pending-state-icon>
                <i class="bi bi-clock" data-pending-state-icon-glyph></i>
            </span>
            <h1 class="section-title text-warning" data-pending-state-title>Pedido pendiente de pago</h1>
            <p data-pending-state-message>Reservamos tus productos mientras completas el pago.</p>

            <div
                class="reservation-countdown border-top border-bottom py-4 my-4"
                data-reservation-countdown
                data-pending-active
                data-expires-at="{{ $order->reservation_expires_at?->toAtomString() }}"
                data-server-now="{{ $serverNow }}"
                data-expiration-url="{{ route('checkout.order.expire', $order->code) }}"
            >
                <span class="small text-muted d-block mb-2">Tiempo restante de la reserva</span>
                <strong class="reservation-countdown-value" data-reservation-countdown-value role="timer">00:00:00</strong>
                <span class="small text-muted d-block mt-2" data-reservation-countdown-status aria-live="polite">
                    Vence el {{ $order->reservation_expires_at?->translatedFormat('d \d\e F \d\e Y, H:i') }}
                </span>
            </div>

            <div class="pending-order-terminal my-4" data-pending-terminal aria-live="assertive" hidden>
                <div class="alert text-start mb-0" data-pending-terminal-alert role="status">
                    <p class="mb-2" data-pending-terminal-reason-wrap hidden>
                        <strong>Motivo:</strong>
                        <span data-pending-terminal-reason></span>
                    </p>
                    <p class="mb-2" data-pending-terminal-refund hidden></p>
                    <p class="small text-muted mb-0" data-pending-terminal-date hidden></p>
                </div>
            </div>

            <dl class="pending-order-summary text-start mb-4">
                <div>
                    <dt>Numero de pedido</dt>
                    <dd class="fw-black">{{ $order->code }}</dd>
                </div>
                <div>
                    <dt>Productos</dt>
                    <dd>{{ $productCount }} ({{ $totalQuantity }} {{ $totalQuantity === 1 ? 'unidad' : 'unidades' }})</dd>
                </div>
                <div>
                    <dt>Total</dt>
                    <dd class="fw-black">{{ $formattedTotal }}</dd>
                </div>
            </dl>

            <div class="alert alert-warning text-start small d-flex gap-2 align-items-start" data-pending-active>
                <i class="bi bi-info-circle-fill" aria-hidden="true"></i>
                <span>El plazo no se extiende al recargar la pagina. Cuando termine, liberaremos los productos automaticamente.</span>
            </div>

            <div class="pending-order-actions" data-pending-active>
                <a class="btn btn-vn" href="{{ route('shop.index') }}">
                    <i class="bi bi-arrow-left" aria-hidden="true"></i>
                    Seguir comprando
                </a>
                <button class="btn btn-outline-danger" type="button" data-bs-toggle="modal" data-bs-target="#cancelPendingOrderModal">
                    <i class="bi bi-x-circle" aria-hidden="true"></i>
                    Cancelar pedido
                </button>
            </div>

            <div class="pending-order-actions" data-pending-terminal-actions hidden>
                <a class="btn btn-vn" href="{{ route('account.orders.show', $order->code) }}" data-pending-detail-link>
                    <i class="bi bi-receipt" aria-hidden="true"></i>
                    Ver detalle del pedido
                </a>
                <a class="btn btn-vn-outline" href="{{ route('shop.index') }}" data-pending-shop-link>
                    <i class="bi bi-bag" aria-hidden="true"></i>
                    Seguir comprando
                </a>
            </div>
        </div>
    </div>
</section>

<div class="modal fade" id="cancelPendingOrderModal" tabindex="-1" aria-labelledby="cancelPendingOrderTitle" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <h2 class="modal-title h5" id="cancelPendingOrderTitle">Cancelar pedido {{ $order->code }}</h2>
                <button class="btn-close" type="button" data-bs-dismiss="modal" aria-label="Cerrar"></button>
            </div>
            <div class="modal-body">
                <p class="mb-2">La reserva se liberara y este pedido quedara cancelado.</p>
                <p class="small text-muted mb-0">Los productos no volveran automaticamente a tu carrito.</p>
            </div>
            <div class="modal-footer">
                <button class="btn btn-outline-secondary" type="button" data-bs-dismiss="modal">Conservar pedido</button>
                <form method="POST" action="{{ route('checkout.order.cancel', $order->code) }}">
                    @csrf
                    @method('DELETE')
                    <button class="btn btn-danger" type="submit">
                        <i class="bi bi-x-circle" aria-hidden="true"></i>
                        Si, cancelar pedido
                    </button>
                </form>
            </div>
        </div>
    </div>
</div>
@endsection
