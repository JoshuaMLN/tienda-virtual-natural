<?php

namespace App\Http\Controllers;

use App\Http\Requests\Checkout\ShowCheckoutRequest;
use App\Support\Cart\CartService;
use App\Support\Checkout\CheckoutDeliveryService;
use App\Support\Checkout\CheckoutFormDataService;
use App\Support\Checkout\CheckoutReadService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Response;
use Illuminate\Support\ViewErrorBag;

class CheckoutController extends Controller
{
    private const EMPTY_CART_WARNING = 'Tu carrito esta vacio. Agrega al menos un producto disponible antes de continuar con el checkout.';

    public function __construct(
        private readonly CheckoutReadService $checkoutReadService,
        private readonly CheckoutFormDataService $checkoutFormDataService,
        private readonly CheckoutDeliveryService $checkoutDeliveryService,
        private readonly CartService $cartService,
    ) {}

    public function __invoke(ShowCheckoutRequest $request): Response|RedirectResponse
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

        $review = $checkoutForm['review'];
        $checkoutForm['is_reviewed'] = is_array($review)
            && $review['legal_is_current']
            && is_string($delivery['quote']['quote_reference'] ?? null)
            && hash_equals(
                $review['delivery_quote_reference'],
                $delivery['quote']['quote_reference'],
            );
        $checkoutForm['max_step'] = $checkoutForm['is_reviewed']
            ? 3
            : ($checkoutForm['has_saved_delivery'] ? 2 : 1);
        $checkoutForm['active_step'] = $this->activeStep(
            $request,
            $checkoutForm['max_step'],
        );

        return response()->view('checkout.index', [
            'checkout' => $checkoutData,
            'checkoutForm' => $checkoutForm,
            'delivery' => $delivery,
        ])->withHeaders([
            'Cache-Control' => 'no-store, private',
            'Pragma' => 'no-cache',
        ]);
    }

    private function activeStep(ShowCheckoutRequest $request, int $maxStep): int
    {
        $requestedStep = $request->integer('paso');
        $activeStep = $requestedStep >= 1 && $requestedStep <= $maxStep
            ? $requestedStep
            : $maxStep;
        $errors = $request->session()->get('errors');

        if ($errors instanceof ViewErrorBag) {
            if ($errors->getBag('checkout')->any()) {
                return 1;
            }

            if ($errors->getBag('checkoutReview')->any()) {
                return min(2, $maxStep);
            }
        }

        return match ($request->session()->get('status')) {
            'checkout-contact-address-saved', 'checkout-fiscal-saved' => min(2, $maxStep),
            'checkout-reviewed' => $maxStep,
            default => $activeStep,
        };
    }
}
