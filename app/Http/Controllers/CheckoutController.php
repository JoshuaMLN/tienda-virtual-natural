<?php

namespace App\Http\Controllers;

use App\Http\Requests\Checkout\ShowCheckoutRequest;
use App\Support\Cart\CartService;
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

        return view('checkout.index', [
            'checkout' => $checkout->toArray(),
            'checkoutForm' => $this->checkoutFormDataService->for($request->user()),
        ]);
    }
}
