<?php

namespace App\Http\Controllers\Account;

use App\Enums\OrderStatus;
use App\Enums\PaymentStatus;
use App\Http\Controllers\Controller;
use App\Support\Checkout\PendingCheckoutOrderService;
use DomainException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class CustomerOrderCancellationController extends Controller
{
    public function __construct(
        private readonly PendingCheckoutOrderService $pendingOrders,
    ) {}

    public function __invoke(Request $request, string $code): RedirectResponse
    {
        $order = $request->user()->orders()
            ->where('code', strtoupper($code))
            ->firstOrFail();
        $redirect = $request->string('return_to')->toString() === 'list'
            ? redirect()->route('account.orders')
            : redirect()->route('account.orders.show', $order->code);

        try {
            $cancelled = $this->pendingOrders->cancel($request->user(), $order);
        } catch (DomainException $exception) {
            $current = $order->refresh();

            if ($current->order_status === OrderStatus::Expired
                || $current->payment_status === PaymentStatus::Expired) {
                return $redirect->with(
                    'order_notice',
                    "El pedido {$current->code} vencio antes de poder cancelarlo y su reserva ya fue liberada.",
                );
            }

            if ($current->payment_status === PaymentStatus::Paid) {
                return $redirect->with(
                    'order_error',
                    'El pago ya fue confirmado. Contacta con la tienda si necesitas solicitar una cancelacion.',
                );
            }

            return $redirect->with('order_error', $exception->getMessage());
        }

        return $redirect->with(
            'order_success',
            "El pedido {$cancelled->code} fue cancelado y su reserva quedo liberada.",
        );
    }
}
