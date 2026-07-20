<?php

namespace App\Support\Checkout;

use App\Models\Product;
use App\Support\Cart\Cart;
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
        return $this->summarize($this->currentCart());
    }

    public function currentCart(): Cart
    {
        return $this->cartService->get();
    }

    public function summarize(Cart $cart): ?CheckoutSummary
    {

        if ($cart->isEmpty()) {
            return null;
        }

        $pricing = $this->pricingService->calculate($this->pricingItems($cart));

        return new CheckoutSummary($cart, $pricing);
    }

    public function withShipping(CheckoutSummary $checkout, int $shippingFeeCents): CheckoutSummary
    {
        $pricing = $this->pricingService->calculate(
            $this->pricingItems($checkout->cart),
            discountCents: $checkout->pricing->discountCents,
            shippingFeeCents: $shippingFeeCents,
        );

        return new CheckoutSummary($checkout->cart, $pricing);
    }

    /** @return list<array{product: Product, quantity: int}> */
    private function pricingItems(Cart $cart): array
    {
        return $cart->items
            ->map(fn (CartItem $item): array => [
                'product' => $item->product,
                'quantity' => $item->quantity,
            ])
            ->all();
    }
}
