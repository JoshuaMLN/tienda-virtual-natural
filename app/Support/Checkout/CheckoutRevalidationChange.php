<?php

namespace App\Support\Checkout;

use App\Enums\CheckoutChangeType;

final readonly class CheckoutRevalidationChange
{
    public function __construct(
        public CheckoutChangeType $type,
        public mixed $previous,
        public mixed $current,
        public ?int $productId = null,
        public ?string $productName = null,
    ) {}

    /** @return array<string, mixed> */
    public function toArray(): array
    {
        return [
            'code' => $this->type->value,
            'scope' => $this->type->scope(),
            'label' => $this->type->label(),
            'product_id' => $this->productId,
            'product_name' => $this->productName,
            'previous' => $this->previous,
            'current' => $this->current,
        ];
    }
}
