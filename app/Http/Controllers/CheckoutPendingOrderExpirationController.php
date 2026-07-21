<?php

namespace App\Http\Controllers;

use App\Enums\OrderStatus;
use App\Models\Order;
use App\Support\Checkout\PendingCheckoutOrderExpirationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class CheckoutPendingOrderExpirationController extends Controller
{
    public function __construct(
        private readonly PendingCheckoutOrderExpirationService $expirations,
    ) {}

    public function __invoke(Request $request, Order $order): JsonResponse
    {
        abort_unless((int) $order->user_id === (int) $request->user()->getKey(), 404);

        $expiredOrder = $this->expirations->expireIfDue($order);

        if ($expiredOrder?->order_status === OrderStatus::Expired) {
            $request->session()->flash(
                'checkout_notice',
                "El pedido {$expiredOrder->code} vencio y su reserva de stock fue liberada.",
            );

            return response()->json([
                'message' => 'La reserva vencio y el stock fue liberado.',
                'status' => OrderStatus::Expired->value,
                'redirect_url' => route('shop.cart'),
            ]);
        }

        $current = $order->refresh();

        if ($current->order_status === OrderStatus::PendingPayment) {
            return response()->json([
                'message' => 'La reserva todavia sigue vigente.',
                'status' => $current->order_status->value,
                'server_now' => now()->toAtomString(),
                'reservation_expires_at' => $current->reservation_expires_at?->toAtomString(),
            ], 409);
        }

        return response()->json([
            'message' => 'El pedido ya no esta pendiente de pago.',
            'status' => $current->order_status->value,
            'redirect_url' => route('shop.cart'),
        ]);
    }
}
