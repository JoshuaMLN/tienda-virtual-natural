<?php

namespace App\Support\Checkout;

use App\Support\Cart\CartItem;
use App\Support\Cart\CartService;
use App\Support\Orders\Pricing\OrderPricingService;

final readonly class CheckoutReadService
{
    public function __construct(
        private CartService $cartService,
        private OrderPricingService $pricingService,
    ) {}

    public function current(): ?CheckoutSummary
    {
        $cart = $this->cartService->get();

        if ($cart->isEmpty()) {
            return null;
        }

        $pricing = $this->pricingService->calculate(
            $cart->items
                ->map(fn (CartItem $item): array => [
                    'product' => $item->product,
                    'quantity' => $item->quantity,
                ])
                ->all(),
        );

        return new CheckoutSummary($cart, $pricing);
    }
}
