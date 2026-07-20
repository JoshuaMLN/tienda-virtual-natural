<?php

namespace App\Http\Controllers;

use App\Http\Requests\Checkout\SaveCheckoutFiscalRequest;
use App\Support\Checkout\CheckoutReviewException;
use App\Support\Checkout\CheckoutReviewService;
use Illuminate\Http\RedirectResponse;

class CheckoutFiscalController extends Controller
{
    public function __construct(
        private readonly CheckoutReviewService $reviewService,
    ) {}

    public function __invoke(SaveCheckoutFiscalRequest $request): RedirectResponse
    {
        try {
            $this->reviewService->saveFiscal(
                $request->user(),
                $request->fiscalAttributes(),
            );
        } catch (CheckoutReviewException $exception) {
            return back()
                ->withInput()
                ->withErrors(['review' => $exception->getMessage()], 'checkoutReview');
        }

        return redirect()
            ->route('checkout.index')
            ->with('status', 'checkout-fiscal-saved');
    }
}
