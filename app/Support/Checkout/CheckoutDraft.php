<?php

namespace App\Support\Checkout;

use App\Enums\DeliveryMethod;

final readonly class CheckoutDraft
{
    public function __construct(
        public int $userId,
        public string $contactName,
        public string $contactPhone,
        public ?int $addressId,
        public ?DeliveryMethod $deliveryMethod = null,
        public ?CheckoutDeliverySnapshot $deliveryQuote = null,
        public ?CheckoutFiscalData $fiscal = null,
        public ?CheckoutReviewSnapshot $review = null,
    ) {}

    /** @return array<string, mixed> */
    public function toArray(): array
    {
        return [
            'schema_version' => 3,
            'user_id' => $this->userId,
            'contact_name' => $this->contactName,
            'contact_phone' => $this->contactPhone,
            'address_id' => $this->addressId,
            'delivery_method' => $this->deliveryMethod?->value,
            'delivery_quote' => $this->deliveryQuote?->toArray(),
            'fiscal' => $this->fiscal?->toArray(),
            'review' => $this->review?->toArray(),
        ];
    }
}
