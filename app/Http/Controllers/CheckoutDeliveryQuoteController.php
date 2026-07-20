<?php

namespace App\Http\Controllers;

use App\Enums\DeliveryMethod;
use App\Http\Requests\Checkout\QuoteCheckoutDeliveryRequest;
use App\Support\Checkout\CheckoutDeliveryService;
use App\Support\Checkout\CheckoutDeliveryUnavailableException;
use App\Support\Checkout\CheckoutReadService;
use Illuminate\Http\JsonResponse;

class CheckoutDeliveryQuoteController extends Controller
{
    public function __construct(
        private readonly CheckoutReadService $checkoutReadService,
        private readonly CheckoutDeliveryService $checkoutDeliveryService,
    ) {}

    public function __invoke(QuoteCheckoutDeliveryRequest $request): JsonResponse
    {
        $cart = $this->checkoutReadService->currentCart();
        $checkout = $this->checkoutReadService->summarize($cart);

        if ($checkout === null) {
            return response()->json([
                'message' => 'Tu carrito esta vacio. Regresa al carrito para continuar.',
                'errors' => ['cart' => ['Tu carrito esta vacio.']],
                'cart' => $cart->toArray(),
                'checkout' => null,
                'redirect_url' => route('shop.cart'),
            ], 409);
        }

        $method = DeliveryMethod::from($request->validated('delivery_method'));

        try {
            if ($method === DeliveryMethod::Pickup) {
                $result = $this->checkoutDeliveryService->pickup($checkout);
            } elseif ($request->validated('address_id') !== null) {
                $result = $this->checkoutDeliveryService->homeForAddress(
                    $request->user(),
                    (int) $request->validated('address_id'),
                    $checkout,
                );
            } else {
                $result = $this->checkoutDeliveryService->homeForUbigeo(
                    (string) $request->validated('ubigeo'),
                    $checkout,
                );
            }
        } catch (CheckoutDeliveryUnavailableException $exception) {
            return response()->json([
                'message' => $exception->getMessage(),
                'delivery' => [
                    'available' => false,
                    'method' => $method->value,
                    ...$this->checkoutDeliveryService->configuration(),
                    'summary' => [
                        'amounts' => $checkout->amountsToArray(),
                    ],
                ],
                'errors' => ['delivery_method' => [$exception->getMessage()]],
                'cart' => $checkout->cart->toArray(),
                'checkout' => $checkout->toArray(),
            ], 422);
        }

        $delivery = $result->toArray();

        return response()->json([
            'message' => $delivery['message'],
            'delivery' => $delivery,
            'errors' => [],
            'cart' => $checkout->cart->toArray(),
            'checkout' => $checkout->toArray(),
        ]);
    }
}
