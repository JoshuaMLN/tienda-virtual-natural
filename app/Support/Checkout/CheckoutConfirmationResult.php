<?php

namespace App\Support\Checkout;

use App\Models\Order;

final readonly class CheckoutConfirmationResult
{
    public function __construct(
        public ?CheckoutRevalidationResult $revalidation = null,
        public ?Order $order = null,
        public bool $idempotentReplay = false,
        public bool $blockedByPendingOrder = false,
    ) {}

    public function created(): bool
    {
        return $this->order !== null && ! $this->blockedByPendingOrder;
    }
}
