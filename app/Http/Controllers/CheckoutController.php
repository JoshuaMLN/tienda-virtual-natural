<?php

namespace App\Http\Controllers;

use App\Http\Requests\Checkout\ShowCheckoutRequest;
use App\Support\Cart\CartService;
use App\Support\Checkout\CheckoutDeliveryService;
use App\Support\Checkout\CheckoutFormDataService;
use App\Support\Checkout\CheckoutReadService;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

class CheckoutController extends Controller
{
    private const EMPTY_CART_WARNING = 'Tu carrito esta vacio. Agrega al menos un producto disponible antes de continuar con el checkout.';

    public function __construct(
        private readonly CheckoutReadService $checkoutReadService,
        private readonly CheckoutFormDataService $checkoutFormDataService,
        private readonly CheckoutDeliveryService $checkoutDeliveryService,
        private readonly CartService $cartService,
    ) {}

    public function __invoke(ShowCheckoutRequest $request): View|RedirectResponse
    {
        $checkout = $this->checkoutReadService->current();

        if ($checkout === null) {
            $this->cartService->rememberWarning(self::EMPTY_CART_WARNING);
            $request->session()->keep(['status']);

            return redirect()->route('shop.cart');
        }

        $checkoutForm = $this->checkoutFormDataService->for($request->user());
        $delivery = $this->checkoutDeliveryService->initialState(
            $request->user(),
            $checkout,
            $checkoutForm['selected_delivery_method'],
            $checkoutForm['selected_address_id'],
        );
        $checkoutData = $checkout->toArray();

        if (is_array($delivery['quote'] ?? null)) {
            $checkoutData['amounts'] = $delivery['quote']['summary']['amounts'];
        }

        return view('checkout.index', [
            'checkout' => $checkoutData,
            'checkoutForm' => $checkoutForm,
            'delivery' => $delivery,
        ]);
    }
}
