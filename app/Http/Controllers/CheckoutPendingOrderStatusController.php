<?php

namespace App\Http\Controllers;

use App\Enums\OrderStatus;
use App\Enums\PaymentStatus;
use App\Models\Order;
use App\Support\Checkout\PendingCheckoutOrderExpirationService;
use App\Support\Orders\CustomerOrderCapabilityResolver;
use App\Support\Orders\CustomerOrderDateFormatter;
use App\Support\Orders\OrderCancellationDetailsResolver;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class CheckoutPendingOrderStatusController extends Controller
{
    public function __construct(
        private readonly PendingCheckoutOrderExpirationService $expirations,
        private readonly CustomerOrderCapabilityResolver $capabilities,
        private readonly OrderCancellationDetailsResolver $cancellations,
        private readonly CustomerOrderDateFormatter $dates,
    ) {}

    public function __invoke(Request $request, Order $order): JsonResponse
    {
        abort_unless((int) $order->user_id === (int) $request->user()->getKey(), 404);

        $current = $this->expirations->expireIfDue($order)
            ?? $order->refresh();
        $current->loadMissing(['stockReservations', 'statusHistories']);
        $capabilities = $this->capabilities->resolve($current);

        if ($capabilities->canContinuePayment) {
            return $this->response([
                'state' => 'pending',
                'terminal' => false,
                'can_continue_payment' => true,
                'server_now' => now()->toAtomString(),
                'reservation_expires_at' => $current->reservation_expires_at?->toAtomString(),
            ]);
        }

        return $this->response(array_merge(
            [
                'terminal' => true,
                'can_continue_payment' => false,
                'detail_url' => route('account.orders.show', $current->code),
                'shop_url' => route('shop.index'),
            ],
            $this->terminalState($current),
        ));
    }

    /** @return array<string, mixed> */
    private function terminalState(Order $order): array
    {
        if ($order->order_status === OrderStatus::Cancelled) {
            $cancellation = $this->cancellations->resolve($order);

            return [
                'state' => 'cancelled',
                'tone' => 'danger',
                'icon' => 'bi-x-lg',
                'title' => $cancellation?->title ?? 'Pedido cancelado',
                'message' => 'Este pedido ya no puede pagarse.',
                'reason' => $cancellation?->reason,
                'refund_message' => $cancellation?->refundMessage,
                'occurred_at' => $cancellation === null
                    ? null
                    : 'Cancelado el '.$this->dates->descriptive($cancellation->occurredAt),
            ];
        }

        if ($order->order_status === OrderStatus::Expired
            || $order->payment_status === PaymentStatus::Expired) {
            return [
                'state' => 'expired',
                'tone' => 'warning',
                'icon' => 'bi-clock-history',
                'title' => 'La reserva de este pedido vencio',
                'message' => 'Los productos reservados fueron liberados y este pedido ya no puede pagarse.',
                'reason' => null,
                'refund_message' => null,
                'occurred_at' => $order->expired_at
                    ? 'Vencido el '.$this->dates->descriptive($order->expired_at)
                    : null,
            ];
        }

        if (in_array($order->payment_status, [
            PaymentStatus::Paid,
            PaymentStatus::RefundPending,
            PaymentStatus::Refunded,
        ], true)) {
            return [
                'state' => 'payment_confirmed',
                'tone' => 'success',
                'icon' => 'bi-check-lg',
                'title' => 'El pago ya fue confirmado',
                'message' => 'Puedes revisar el estado actualizado desde el detalle de tu pedido.',
                'reason' => null,
                'refund_message' => null,
                'occurred_at' => null,
            ];
        }

        return [
            'state' => 'unavailable',
            'tone' => 'warning',
            'icon' => 'bi-info-lg',
            'title' => 'Este pedido ya no esta disponible para pago',
            'message' => 'Revisa el detalle del pedido para conocer su estado actual.',
            'reason' => null,
            'refund_message' => null,
            'occurred_at' => null,
        ];
    }

    /** @param array<string, mixed> $data */
    private function response(array $data): JsonResponse
    {
        return response()
            ->json($data)
            ->withHeaders([
                'Cache-Control' => 'no-store, private',
                'Pragma' => 'no-cache',
            ]);
    }
}
