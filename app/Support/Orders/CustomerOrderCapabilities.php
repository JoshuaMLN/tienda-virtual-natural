<?php

namespace App\Support\Orders;

final readonly class CustomerOrderCapabilities
{
    public function __construct(
        public bool $canCancel,
        public bool $canContinuePayment,
        public bool $shouldContactSupport,
    ) {}
}
