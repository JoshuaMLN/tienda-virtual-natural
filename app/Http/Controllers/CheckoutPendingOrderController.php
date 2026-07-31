<?php

namespace App\Http\Controllers;

use App\Enums\OrderStatus;
use App\Models\Order;
use App\Support\Checkout\PendingCheckoutOrderExpirationService;
use App\Support\Checkout\PendingCheckoutOrderService;
use App\Support\Money\Money;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

class CheckoutPendingOrderController extends Controller
{
    public function __construct(
        private readonly PendingCheckoutOrderService $pendingOrders,
        private readonly PendingCheckoutOrderExpirationService $expirations,
    ) {}

    public function __invoke(Request $request, Order $order): Response|RedirectResponse
    {
        abort_unless((int) $order->user_id === (int) $request->user()->getKey(), 404);

        $expiredOrder = $this->expirations->expireIfDue($order);

        if ($expiredOrder?->order_status === OrderStatus::Expired) {
            return redirect()
                ->route('shop.cart')
                ->with('checkout_notice', "El pedido {$expiredOrder->code} vencio y su reserva de stock fue liberada.");
        }

        if (! $this->pendingOrders->isPending($order)) {
            return redirect()->route('account.orders.show', $order->code);
        }

        $order->loadMissing('items');

        return response()->view('checkout.pending', [
            'order' => $order,
            'formattedTotal' => Money::fromCents($order->total_cents)->formatted(),
            'productCount' => $order->items->count(),
            'totalQuantity' => $order->items->sum('quantity'),
            'serverNow' => now()->toAtomString(),
        ])->withHeaders([
            'Cache-Control' => 'no-store, private',
            'Pragma' => 'no-cache',
        ]);
    }
}
