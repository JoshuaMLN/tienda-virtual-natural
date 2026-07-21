<?php

namespace App\Http\Controllers;

use App\Models\Order;
use App\Support\Checkout\PendingCheckoutOrderService;
use DomainException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class CheckoutPendingOrderCancellationController extends Controller
{
    public function __construct(
        private readonly PendingCheckoutOrderService $pendingOrders,
    ) {}

    public function __invoke(Request $request, Order $order): RedirectResponse
    {
        abort_unless((int) $order->user_id === (int) $request->user()->getKey(), 404);

        try {
            $cancelled = $this->pendingOrders->cancel($request->user(), $order);
        } catch (DomainException $exception) {
            return redirect()
                ->route('shop.cart')
                ->with('checkout_error', $exception->getMessage());
        }

        return redirect()
            ->route('shop.cart')
            ->with('checkout_success', "El pedido {$cancelled->code} fue cancelado y su reserva quedo liberada.");
    }
}
