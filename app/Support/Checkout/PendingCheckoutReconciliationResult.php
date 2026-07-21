<?php

namespace App\Support\Checkout;

use App\Models\Order;

final readonly class PendingCheckoutReconciliationResult
{
    /** @param list<string> $expiredOrderCodes */
    public function __construct(
        public ?Order $pendingOrder,
        public array $expiredOrderCodes = [],
    ) {}
}
