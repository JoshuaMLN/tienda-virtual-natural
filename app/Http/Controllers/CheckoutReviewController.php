<?php

namespace App\Http\Controllers;

use App\Http\Requests\Checkout\ReviewCheckoutRequest;
use App\Support\Cart\CartService;
use App\Support\Checkout\CheckoutDeliveryUnavailableException;
use App\Support\Checkout\CheckoutQuoteChangedException;
use App\Support\Checkout\CheckoutReviewException;
use App\Support\Checkout\CheckoutReviewService;
use Illuminate\Http\RedirectResponse;

class CheckoutReviewController extends Controller
{
    public function __construct(
        private readonly CheckoutReviewService $reviewService,
        private readonly CartService $cartService,
    ) {}

    public function __invoke(ReviewCheckoutRequest $request): RedirectResponse
    {
        try {
            $this->reviewService->review(
                $request->user(),
                $request->fiscalAttributes(),
                (int) $request->validated('terms_document_id'),
            );
        } catch (CheckoutQuoteChangedException $exception) {
            return back()
                ->withInput()
                ->withErrors(['quote_reference' => $exception->getMessage()], 'checkoutReview');
        } catch (CheckoutReviewException|CheckoutDeliveryUnavailableException $exception) {
            return back()
                ->withInput()
                ->withErrors(['review' => $exception->getMessage()], 'checkoutReview');
        }

        $this->cartService->clearWarnings();

        return redirect()
            ->route('checkout.index')
            ->with('status', 'checkout-reviewed');
    }
}
