<?php

namespace App\Support\Orders;

use Carbon\CarbonInterface;

final readonly class OrderCancellationDetails
{
    public function __construct(
        public bool $initiatedByCustomer,
        public string $title,
        public string $reason,
        public CarbonInterface $occurredAt,
        public ?string $refundMessage,
    ) {}
}
